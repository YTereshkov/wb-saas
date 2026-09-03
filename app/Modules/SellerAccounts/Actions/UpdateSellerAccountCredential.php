<?php

namespace App\Modules\SellerAccounts\Actions;

use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Wildberries\Clients\WildberriesHttpClientFactory;
use App\Modules\Wildberries\Exceptions\WildberriesApiException;
use App\Modules\Wildberries\Support\WildberriesTokenInspector;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class UpdateSellerAccountCredential
{
    public function __construct(
        private UpdateDemoSellerAccountCredential $updateDemo,
        private WildberriesHttpClientFactory $httpClientFactory,
        private StoreSellerAccountCredential $storeCredential,
        private WildberriesTokenInspector $tokenInspector,
    ) {}

    public function handle(SellerAccount $account, string $token): void
    {
        if ($account->source === SellerAccountSource::Demo) {
            $this->updateDemo->handle($account, $token);

            return;
        }

        $isSandbox = $this->tokenInspector->isTest($token);

        try {
            $client = $isSandbox
                ? $this->httpClientFactory->forSandboxToken($token, $account->timezone)
                : $this->httpClientFactory->forToken($token, $account->timezone);
            $connection = $client->checkConnection();
        } catch (WildberriesApiException $exception) {
            Log::warning('Wildberries credential update failed.', [
                'seller_account_id' => $account->id,
                'error_code' => $exception->errorCode,
                'http_status' => $exception->httpStatus,
            ]);

            throw ValidationException::withMessages([
                'token' => $exception->isAuthorizationFailure()
                    ? 'Токен Wildberries недействителен или отозван.'
                    : 'Не удалось проверить токен Wildberries. Повторите попытку позже.',
            ]);
        }

        if ($connection->externalAccountId !== $account->wb_account_id) {
            throw ValidationException::withMessages([
                'token' => 'Токен принадлежит другому кабинету Wildberries.',
            ]);
        }
        if (array_diff(['products', 'orders', 'sales'], $connection->availableResources) !== []) {
            throw ValidationException::withMessages([
                'token' => 'Токену нужны доступы Content и Statistics.',
            ]);
        }
        if (! $isSandbox && ! $this->tokenInspector->isReadOnly($token)) {
            throw ValidationException::withMessages([
                'token' => 'Создайте токен Wildberries с доступом «Только чтение».',
            ]);
        }

        $this->storeCredential->handle($account, $token, $connection->availableResources, now());
        $account->update([
            'status' => SellerAccountStatus::Active,
            'disconnected_at' => null,
        ]);
    }
}
