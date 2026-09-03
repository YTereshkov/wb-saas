<?php

namespace App\Modules\Wildberries\Normalizers;

use App\Modules\Wildberries\Exceptions\WildberriesApiException;
use Carbon\CarbonImmutable;
use Throwable;

abstract class AbstractWildberriesNormalizer
{
    /** @param array<string, mixed> $payload */
    protected function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if ((! is_string($value) && ! is_int($value)) || (string) $value === '') {
            throw WildberriesApiException::schemaDrift();
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $payload */
    protected function integer(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw WildberriesApiException::schemaDrift();
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $payload */
    protected function money(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (! is_int($value) && ! is_float($value) && ! is_numeric($value)) {
            throw WildberriesApiException::schemaDrift();
        }

        return (int) round((float) $value * 100);
    }

    /** @param array<string, mixed> $payload */
    protected function positiveMoney(array $payload, string $key): int
    {
        $value = $this->money($payload, $key);
        if ($value < 0) {
            throw WildberriesApiException::schemaDrift();
        }

        return $value;
    }

    protected function date(string $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value, 'Europe/Moscow')->utc();
        } catch (Throwable $exception) {
            throw new WildberriesApiException('schema_drift', false, previous: $exception);
        }
    }
}
