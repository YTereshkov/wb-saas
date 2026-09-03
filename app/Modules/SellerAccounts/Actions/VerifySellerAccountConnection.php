<?php

namespace App\Modules\SellerAccounts\Actions;

use App\Models\User;
use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Wildberries\Clients\WildberriesHttpClientFactory;
use App\Modules\Wildberries\Data\ConnectionCheckData;
use App\Modules\Wildberries\Exceptions\WildberriesApiException;
use App\Modules\Wildberries\Support\WildberriesTokenInspector;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class VerifySellerAccountConnection
{
    private const REQUIRED_RESOURCES = ['products', 'orders', 'sales'];

    public function __construct(
        private VerifyDemoSellerAccountConnection $verifyDemo,
        private WildberriesHttpClientFactory $httpClientFactory,
        private CreateSellerAccount $createSellerAccount,
        private StoreSellerAccountCredential $storeCredential,
        private WildberriesTokenInspector $tokenInspector,
    ) {}

    public function handle(User $user, string $token): SellerAccount
    {
        if (hash_equals((string) config('sellerscope.demo.connection_token'), $token)) {
            return $this->verifyDemo->handle($user, $token);
        }

        $isSandbox = $this->tokenInspector->isTest($token);

        try {
            $client = $isSandbox
                ? $this->httpClientFactory->forSandboxToken($token)
                : $this->httpClientFactory->forToken($token);
            $connection = $client->checkConnection();
        } catch (WildberriesApiException $exception) {
            Log::warning('Wildberries credential verification failed.', [
                'user_id' => $user->id,
                'error_code' => $exception->errorCode,
                'http_status' => $exception->httpStatus,
            ]);

            throw ValidationException::withMessages([
                'token' => $exception->isAuthorizationFailure()
                    ? 'Токен Wildberries недействителен или отозван.'
                    : 'Не удалось проверить токен Wildberries. Повторите попытку позже.',
            ]);
        }

        $this->ensureRequiredResources($connection);
        if (! $isSandbox) {
            $this->ensureReadOnly($token);
        }

        return DB::transaction(function () use ($user, $token, $connection): SellerAccount {
            $account = $user->sellerAccounts()
                ->where('source', SellerAccountSource::Wildberries)
                ->where('wb_account_id', $connection->externalAccountId)
                ->first();

            if ($account === null) {
                $account = $this->createSellerAccount->handle(
                    $user,
                    $connection->accountName ?? 'Кабинет Wildberries',
                    SellerAccountSource::Wildberries,
                    $connection->externalAccountId,
                );
            }

            $this->storeCredential->handle(
                $account,
                $token,
                $connection->availableResources,
                now(),
            );
            $account->update([
                'name' => $connection->accountName ?? $account->name,
                'status' => SellerAccountStatus::Verified,
                'disconnected_at' => null,
            ]);

            $now = CarbonImmutable::now($account->timezone);
            $currentStart = $now->startOfMonth();
            $previousStart = $currentStart->subMonth();
            $user->preference()->firstOrCreate()->update([
                'active_seller_account_id' => $account->id,
                'period_preset' => 'month',
                'period_start' => $currentStart->toDateString(),
                'period_end' => $now->toDateString(),
                'comparison_start' => $previousStart->toDateString(),
                'comparison_end' => $previousStart->endOfMonth()->toDateString(),
            ]);

            return $account->fresh();
        });
    }

    private function ensureRequiredResources(ConnectionCheckData $connection): void
    {
        $missing = array_diff(self::REQUIRED_RESOURCES, $connection->availableResources);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'token' => 'Токену нужны доступы Content и Statistics.',
            ]);
        }
    }

    private function ensureReadOnly(string $token): void
    {
        if (! $this->tokenInspector->isReadOnly($token)) {
            throw ValidationException::withMessages([
                'token' => 'Создайте токен Wildberries с доступом «Только чтение».',
            ]);
        }
    }
}
