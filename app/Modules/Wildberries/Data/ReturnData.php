<?php

namespace App\Modules\Wildberries\Data;

use App\Modules\Wildberries\Contracts\CanonicalData;
use Carbon\CarbonImmutable;

final readonly class ReturnData implements CanonicalData
{
    public function __construct(
        public string $externalId,
        public ?string $saleExternalId,
        public string $srid,
        public string $productNmId,
        public CarbonImmutable $returnedAt,
        public int $quantity,
        public int $amountKopecks,
        public CarbonImmutable $updatedAt,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'sale_external_id' => $this->saleExternalId,
            'srid' => $this->srid,
            'product_nm_id' => $this->productNmId,
            'returned_at' => $this->returnedAt->toIso8601String(),
            'quantity' => $this->quantity,
            'amount_kopecks' => $this->amountKopecks,
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
