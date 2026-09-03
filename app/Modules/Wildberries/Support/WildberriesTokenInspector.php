<?php

namespace App\Modules\Wildberries\Support;

final readonly class WildberriesTokenInspector
{
    private const READ_ONLY_BIT = 30;

    /** @var list<int> */
    private const CURRENT_STOCKS_TOKEN_TYPES = [3, 4];

    public function isReadOnly(string $token): bool
    {
        $claims = $this->claims($token);
        $scope = is_array($claims) ? ($claims['s'] ?? null) : null;

        return is_int($scope) || (is_string($scope) && ctype_digit($scope))
            ? (((int) $scope & (1 << self::READ_ONLY_BIT)) !== 0)
            : false;
    }

    public function isTest(string $token): bool
    {
        $claims = $this->claims($token);

        return is_array($claims) && ($claims['t'] ?? null) === true;
    }

    public function supportsCurrentStocks(string $token): bool
    {
        $claims = $this->claims($token);
        $accountType = is_array($claims) ? ($claims['acc'] ?? null) : null;
        if (is_string($accountType) && ctype_digit($accountType)) {
            $accountType = (int) $accountType;
        }

        // Legacy tokens do not expose acc and remain compatible with this endpoint.
        return $accountType === null
            || (is_int($accountType) && in_array($accountType, self::CURRENT_STOCKS_TOKEN_TYPES, true));
    }

    public function sellerAccountId(string $token): ?string
    {
        $claims = $this->claims($token);
        $accountId = is_array($claims) ? ($claims['sid'] ?? null) : null;

        return is_string($accountId) && $accountId !== '' && strlen($accountId) <= 255
            ? $accountId
            : null;
    }

    /** @return array<string, mixed>|null */
    private function claims(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null;
        }

        $claims = json_decode($decoded, true);

        return is_array($claims) && ! array_is_list($claims) ? $claims : null;
    }
}
