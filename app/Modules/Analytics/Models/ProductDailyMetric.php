<?php

namespace App\Modules\Analytics\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $seller_account_id
 * @property int $product_id
 * @property CarbonImmutable $metric_date
 * @property int $revenue_kopecks
 * @property int $orders
 * @property int $sales
 * @property int $returns
 */
#[Fillable([
    'seller_account_id',
    'product_id',
    'metric_date',
    'revenue_kopecks',
    'orders',
    'sales',
    'returns',
])]
class ProductDailyMetric extends Model
{
    /** @return BelongsTo<SellerAccount, $this> */
    public function sellerAccount(): BelongsTo
    {
        return $this->belongsTo(SellerAccount::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metric_date' => 'immutable_date',
            'revenue_kopecks' => 'integer',
            'orders' => 'integer',
            'sales' => 'integer',
            'returns' => 'integer',
        ];
    }
}
