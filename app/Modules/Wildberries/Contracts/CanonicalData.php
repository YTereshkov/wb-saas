<?php

namespace App\Modules\Wildberries\Contracts;

interface CanonicalData
{
    /** @return array<string, bool|int|string|null> */
    public function toArray(): array;
}
