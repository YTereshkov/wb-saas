<?php

namespace Tests\Unit\Synchronization;

use App\Modules\Synchronization\Support\SyncRetryDelay;
use App\Modules\Wildberries\Exceptions\WildberriesApiException;
use Tests\TestCase;

class SyncRetryDelayTest extends TestCase
{
    public function test_api_retry_after_has_priority(): void
    {
        $delay = app(SyncRetryDelay::class)->forException(
            new WildberriesApiException('rate_limited', true, 17),
            3,
            'account-1',
        );

        $this->assertSame(17, $delay);
    }

    public function test_api_retry_after_is_not_clamped_by_transient_backoff_maximum(): void
    {
        config()->set('sellerscope.synchronization.retry_max_seconds', 120);

        $delay = app(SyncRetryDelay::class)->forException(
            new WildberriesApiException('rate_limited', true, 600),
            1,
            'account-1',
        );

        $this->assertSame(600, $delay);
    }

    public function test_api_retry_after_is_clamped_to_one_day(): void
    {
        config()->set('sellerscope.synchronization.retry_after_max_seconds', 86_400);

        $delay = app(SyncRetryDelay::class)->forException(
            new WildberriesApiException('rate_limited', true, 100_000),
            1,
            'account-1',
        );

        $this->assertSame(86_400, $delay);
    }

    public function test_transient_errors_use_bounded_exponential_backoff_with_stable_jitter(): void
    {
        config()->set('sellerscope.synchronization.retry_base_seconds', 30);
        config()->set('sellerscope.synchronization.retry_max_seconds', 900);
        $retry = app(SyncRetryDelay::class);
        $exception = new WildberriesApiException('connection_failed', true);

        $first = $retry->forException($exception, 1, 'account-1');
        $second = $retry->forException($exception, 2, 'account-1');
        $late = $retry->forException($exception, 20, 'account-1');

        $this->assertGreaterThanOrEqual(30, $first);
        $this->assertLessThanOrEqual(45, $first);
        $this->assertGreaterThan($first, $second);
        $this->assertLessThanOrEqual(900, $late);
        $this->assertSame($second, $retry->forException($exception, 2, 'account-1'));
    }
}
