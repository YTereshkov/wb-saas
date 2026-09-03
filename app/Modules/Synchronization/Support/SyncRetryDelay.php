<?php

namespace App\Modules\Synchronization\Support;

use App\Modules\Wildberries\Exceptions\WildberriesApiException;

final class SyncRetryDelay
{
    public function forException(
        WildberriesApiException $exception,
        int $attempt,
        string $seed,
    ): int {
        if ($exception->retryAfterSeconds !== null) {
            $retryAfterMaximum = max(1, (int) config(
                'sellerscope.synchronization.retry_after_max_seconds',
                86_400,
            ));

            return max(1, min($retryAfterMaximum, $exception->retryAfterSeconds));
        }

        $configuredMaximum = max(1, (int) config('sellerscope.synchronization.retry_max_seconds', 900));
        $base = max(1, (int) config('sellerscope.synchronization.retry_base_seconds', 30));
        $maximum = max($base, $configuredMaximum);
        $exponent = min(6, max(0, $attempt - 1));
        $delay = min($maximum, $base * (2 ** $exponent));
        $jitterRange = max(1, intdiv($base, 2));
        $jitter = crc32($seed.'|'.$attempt) % ($jitterRange + 1);

        return min($maximum, $delay + $jitter);
    }
}
