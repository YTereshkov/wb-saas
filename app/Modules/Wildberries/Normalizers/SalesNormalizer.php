<?php

namespace App\Modules\Wildberries\Normalizers;

use App\Modules\Wildberries\Data\ReturnData;
use App\Modules\Wildberries\Data\SaleData;
use App\Modules\Wildberries\Exceptions\WildberriesApiException;

final class SalesNormalizer extends AbstractWildberriesNormalizer
{
    /** @param array<string, mixed> $payload */
    public function normalize(array $payload): SaleData|ReturnData
    {
        $externalId = $this->string($payload, 'saleID');
        $srid = $this->string($payload, 'srid');
        $productNmId = $this->string($payload, 'nmId');
        $eventAt = $this->date($this->string($payload, 'date'));
        $updatedAt = $this->date($this->string($payload, 'lastChangeDate'));
        $isReturn = str_starts_with(strtoupper($externalId), 'R');
        $amount = $this->money($payload, 'finishedPrice');

        if ($isReturn) {
            return new ReturnData(
                externalId: $externalId,
                saleExternalId: null,
                srid: $srid,
                productNmId: $productNmId,
                returnedAt: $eventAt,
                quantity: 1,
                amountKopecks: abs($amount),
                updatedAt: $updatedAt,
            );
        }

        if ($amount < 0) {
            throw WildberriesApiException::schemaDrift();
        }

        return new SaleData(
            externalId: $externalId,
            srid: $srid,
            productNmId: $productNmId,
            soldAt: $eventAt,
            quantity: 1,
            amountKopecks: $amount,
            updatedAt: $updatedAt,
        );
    }
}
