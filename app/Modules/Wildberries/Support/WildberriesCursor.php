<?php

namespace App\Modules\Wildberries\Support;

use InvalidArgumentException;
use JsonException;

final class WildberriesCursor
{
    /** @param array<string, mixed> $payload */
    public static function encode(string $resource, array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');

        return "wb1:{$resource}:{$encoded}";
    }

    /** @return array<string, mixed> */
    public static function decode(?string $cursor, string $resource): array
    {
        if ($cursor === null) {
            return [];
        }

        $prefix = "wb1:{$resource}:";
        if (! str_starts_with($cursor, $prefix)) {
            throw new InvalidArgumentException("Invalid {$resource} cursor.");
        }

        $encoded = substr($cursor, strlen($prefix));
        $padding = (4 - strlen($encoded) % 4) % 4;
        $json = base64_decode(strtr($encoded.str_repeat('=', $padding), '-_', '+/'), true);

        if ($json === false) {
            throw new InvalidArgumentException("Invalid {$resource} cursor.");
        }

        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("Invalid {$resource} cursor.", previous: $exception);
        }

        if (! is_array($payload)) {
            throw new InvalidArgumentException("Invalid {$resource} cursor.");
        }

        return $payload;
    }
}
