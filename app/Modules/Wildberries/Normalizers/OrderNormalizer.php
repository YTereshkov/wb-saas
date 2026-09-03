<?php

namespace App\Modules\Wildberries\Normalizers;

use App\Modules\Wildberries\Data\OrderData;

final class OrderNormalizer extends AbstractWildberriesNormalizer
{
    /** @param array<string, mixed> $payload */
    public function normalize(array $payload): OrderData
    {
        $cancelled = ($payload['isCancel'] ?? false) === true;
        $cancelledAt = null;
        if ($cancelled && is_string($payload['cancelDate'] ?? null)) {
            $cancelledAt = $this->date($payload['cancelDate']);
        }

        return new OrderData(
            srid: $this->string($payload, 'srid'),
            productNmId: $this->string($payload, 'nmId'),
            orderedAt: $this->date($this->string($payload, 'date')),
            quantity: 1,
            amountKopecks: $this->positiveMoney($payload, 'finishedPrice'),
            status: $cancelled ? 'cancelled' : 'ordered',
            cancelled: $cancelled,
            cancelledAt: $cancelledAt,
            updatedAt: $this->date($this->string($payload, 'lastChangeDate')),
        );
    }
}
