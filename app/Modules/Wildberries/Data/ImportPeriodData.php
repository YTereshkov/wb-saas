<?php

namespace App\Modules\Wildberries\Data;

use Carbon\CarbonImmutable;

final readonly class ImportPeriodData
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        if ($start->isAfter($end)) {
            throw new \InvalidArgumentException('Import period start must not be after its end.');
        }
    }
}
