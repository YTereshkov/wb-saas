<?php

namespace App\Modules\Wildberries\Data;

use App\Modules\Wildberries\Contracts\CanonicalData;
use Carbon\CarbonImmutable;

final readonly class StockData implements CanonicalData
{
    public function __construct(
        public string $productNmId,
        public string $warehouseExternalId,
        public string $warehouseName,
        public string $warehouseType,
        public ?string $warehouseRegion,
        public string $variantExternalId,
        public CarbonImmutable $snapshotAt,
        public int $quantity,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'product_nm_id' => $this->productNmId,
            'warehouse_external_id' => $this->warehouseExternalId,
            'warehouse_name' => $this->warehouseName,
            'warehouse_type' => $this->warehouseType,
            'warehouse_region' => $this->warehouseRegion,
            'variant_external_id' => $this->variantExternalId,
            'snapshot_at' => $this->snapshotAt->toIso8601String(),
            'quantity' => $this->quantity,
        ];
    }
}
