<?php

namespace App\Modules\Wildberries\Data;

use App\Modules\Wildberries\Contracts\CanonicalData;
use Carbon\CarbonImmutable;

final readonly class OrderData implements CanonicalData
{
    public function __construct(
        public string $srid,
        public string $productNmId,
        public CarbonImmutable $orderedAt,
        public int $quantity,
        public int $amountKopecks,
        public string $status,
        public bool $cancelled,
        public ?CarbonImmutable $cancelledAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'srid' => $this->srid,
            'product_nm_id' => $this->productNmId,
            'ordered_at' => $this->orderedAt->toIso8601String(),
            'quantity' => $this->quantity,
            'amount_kopecks' => $this->amountKopecks,
            'status' => $this->status,
            'cancelled' => $this->cancelled,
            'cancelled_at' => $this->cancelledAt?->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
