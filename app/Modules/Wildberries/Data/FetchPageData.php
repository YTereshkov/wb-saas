<?php

namespace App\Modules\Wildberries\Data;

/** @template T */
final readonly class FetchPageData
{
    /**
     * @param  list<T>  $items
     * @param  array<mixed>|null  $rawPayload
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public ?string $checkpoint = null,
        public ?array $rawPayload = null,
    ) {}
}
