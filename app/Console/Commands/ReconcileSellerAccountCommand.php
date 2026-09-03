<?php

namespace App\Console\Commands;

use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Actions\ReconcileSellerAccount;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

final class ReconcileSellerAccountCommand extends Command
{
    protected $signature = 'sellerscope:reconcile
        {sellerAccount : Internal seller account ID}
        {--start= : First local business date, YYYY-MM-DD}
        {--end= : Last local business date, YYYY-MM-DD}
        {--json : Print machine-readable output}
        {--fail-on-drift : Return a non-zero exit code when drift is found}';

    protected $description = 'Reconcile normalized facts with account and product daily metrics';

    public function handle(ReconcileSellerAccount $action): int
    {
        $identifier = (string) $this->argument('sellerAccount');
        if (! ctype_digit($identifier) || (int) $identifier < 1) {
            $this->error('Seller account ID must be a positive integer.');

            return self::INVALID;
        }

        $account = SellerAccount::query()->find((int) $identifier);
        if ($account === null) {
            $this->error('Seller account was not found.');

            return self::FAILURE;
        }

        try {
            $end = $this->dateOption('end', $account->timezone)
                ?? CarbonImmutable::now($account->timezone)->startOfDay();
            $start = $this->dateOption('start', $account->timezone)
                ?? $end->subDays(29);
        } catch (Throwable) {
            $this->error('Dates must use the YYYY-MM-DD format.');

            return self::INVALID;
        }

        if ($end->lt($start)) {
            $this->error('The end date must not precede the start date.');

            return self::INVALID;
        }

        $result = $action->handle($account, $start, $end);

        if ((bool) $this->option('json')) {
            $this->line(json_encode(
                $result,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ));
        } else {
            $this->info(sprintf(
                'Seller account %d, %s to %s: %s',
                $account->id,
                $result['start'],
                $result['end'],
                $result['passed'] ? 'PASS' : 'DRIFT',
            ));
            $this->table(
                ['Check', 'Expected', 'Actual', 'Difference', 'Status'],
                array_map(static fn (array $check): array => [
                    $check['key'],
                    $check['expected'],
                    $check['actual'],
                    $check['difference'],
                    $check['passed'] ? 'OK' : 'DRIFT',
                ], $result['checks']),
            );
        }

        return ! $result['passed'] && (bool) $this->option('fail-on-drift')
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function dateOption(string $name, string $timezone): ?CarbonImmutable
    {
        $value = $this->option($name);

        if (! is_string($value) || $value === '') {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        if ($date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Date was normalized.');
        }

        return $date;
    }
}
