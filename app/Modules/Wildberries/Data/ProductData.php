<?php

namespace App\Modules\Wildberries\Data;

use App\Modules\Wildberries\Contracts\CanonicalData;
use Carbon\CarbonImmutable;

final readonly class ProductData implements CanonicalData
{
    public function __construct(
        public string $nmId,
        public string $vendorCode,
        public string $title,
        public string $brand,
        public string $categoryExternalId,
        public string $categoryName,
        public ?string $imageUrl,
        public bool $active,
        public CarbonImmutable $updatedAt,
    ) {}

    /** @return array<string, bool|string|null> */
    public function toArray(): array
    {
        return [
            'nm_id' => $this->nmId,
            'vendor_code' => $this->vendorCode,
            'title' => $this->title,
            'brand' => $this->brand,
            'category_external_id' => $this->categoryExternalId,
            'category_name' => $this->categoryName,
            'image_url' => $this->imageUrl,
            'active' => $this->active,
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
