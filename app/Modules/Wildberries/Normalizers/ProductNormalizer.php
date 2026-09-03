<?php

namespace App\Modules\Wildberries\Normalizers;

use App\Modules\Wildberries\Data\ProductData;

final class ProductNormalizer extends AbstractWildberriesNormalizer
{
    /** @param array<string, mixed> $payload */
    public function normalize(array $payload): ProductData
    {
        $photos = $payload['photos'] ?? [];
        $imageUrl = is_array($photos)
            && isset($photos[0])
            && is_array($photos[0])
            && is_string($photos[0]['big'] ?? null)
                ? $photos[0]['big']
                : null;

        return new ProductData(
            nmId: $this->string($payload, 'nmID'),
            vendorCode: $this->string($payload, 'vendorCode'),
            title: $this->string($payload, 'title'),
            brand: is_string($payload['brand'] ?? null) ? $payload['brand'] : '',
            categoryExternalId: $this->string($payload, 'subjectID'),
            categoryName: $this->string($payload, 'subjectName'),
            imageUrl: $imageUrl,
            active: true,
            updatedAt: $this->date($this->string($payload, 'updatedAt')),
        );
    }
}
