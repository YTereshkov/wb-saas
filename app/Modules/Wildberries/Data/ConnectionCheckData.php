<?php

namespace App\Modules\Wildberries\Data;

final readonly class ConnectionCheckData
{
    /** @param list<string> $availableResources */
    public function __construct(
        public bool $successful,
        public string $externalAccountId,
        public array $availableResources,
        public ?string $accountName = null,
    ) {}
}
