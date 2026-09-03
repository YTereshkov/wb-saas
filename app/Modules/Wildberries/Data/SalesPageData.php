<?php

namespace App\Modules\Wildberries\Data;

final readonly class SalesPageData
{
    /**
     * @param  list<SaleData>  $sales
     * @param  list<ReturnData>  $returns
     * @param  array<mixed>|null  $rawPayload
     */
    public function __construct(
        public array $sales,
        public array $returns,
        public ?string $nextCursor,
        public ?string $checkpoint = null,
        public ?array $rawPayload = null,
    ) {}
}
