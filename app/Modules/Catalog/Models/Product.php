<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Analytics\Models\ProductDailyMetric;
use App\Modules\Analytics\Models\ProductSignal;
use App\Modules\Inventory\Models\StockSnapshot;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\ReturnFact;
use App\Modules\Sales\Models\Sale;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $seller_account_id
 * @property int|null $category_id
 * @property string $nm_id
 * @property string $vendor_code
 * @property string $title
 * @property string|null $brand
 * @property string|null $image_url
 * @property bool $is_active
 * @property Carbon|null $external_updated_at
 * @property-read SellerAccount $sellerAccount
 * @property-read Category|null $category
 */
#[Fillable([
    'seller_account_id',
    'category_id',
    'nm_id',
    'vendor_code',
    'title',
    'brand',
    'image_url',
    'is_active',
    'external_updated_at',
])]
class Product extends Model
{
    /** @return BelongsTo<SellerAccount, $this> */
    public function sellerAccount(): BelongsTo
    {
        return $this->belongsTo(SellerAccount::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return HasMany<ReturnFact, $this> */
    public function returnFacts(): HasMany
    {
        return $this->hasMany(ReturnFact::class);
    }

    /** @return HasMany<StockSnapshot, $this> */
    public function stockSnapshots(): HasMany
    {
        return $this->hasMany(StockSnapshot::class);
    }

    /** @return HasMany<ProductDailyMetric, $this> */
    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(ProductDailyMetric::class);
    }

    /** @return HasMany<ProductSignal, $this> */
    public function signals(): HasMany
    {
        return $this->hasMany(ProductSignal::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'external_updated_at' => 'immutable_datetime',
        ];
    }
}
