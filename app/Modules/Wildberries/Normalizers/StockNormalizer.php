<?php

namespace App\Modules\Wildberries\Normalizers;

use App\Modules\Wildberries\Data\StockData;
use Carbon\CarbonImmutable;

final class StockNormalizer extends AbstractWildberriesNormalizer
{
    /** @param array<string, mixed> $payload */
    public function normalize(array $payload, CarbonImmutable $snapshotAt): StockData
    {
        return new StockData(
            productNmId: $this->string($payload, 'nmId'),
            warehouseExternalId: $this->string($payload, 'warehouseId'),
            warehouseName: $this->string($payload, 'warehouseName'),
            warehouseType: 'wildberries',
            warehouseRegion: is_string($payload['regionName'] ?? null) ? $payload['regionName'] : null,
            variantExternalId: $this->string($payload, 'chrtId'),
            snapshotAt: $snapshotAt,
            quantity: $this->integer($payload, 'quantity'),
        );
    }
}
