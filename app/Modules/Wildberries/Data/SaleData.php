<?php

namespace App\Modules\Wildberries\Data;

use App\Modules\Wildberries\Contracts\CanonicalData;
use Carbon\CarbonImmutable;

final readonly class SaleData implements CanonicalData
{
    public function __construct(
        public string $externalId,
        public string $srid,
        public string $productNmId,
        public CarbonImmutable $soldAt,
        public int $quantity,
        public int $amountKopecks,
        public CarbonImmutable $updatedAt,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'srid' => $this->srid,
            'product_nm_id' => $this->productNmId,
            'sold_at' => $this->soldAt->toIso8601String(),
            'quantity' => $this->quantity,
            'amount_kopecks' => $this->amountKopecks,
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
