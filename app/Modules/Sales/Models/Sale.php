<?php

namespace App\Modules\Sales\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $seller_account_id
 * @property int $product_id
 * @property int|null $order_id
 * @property string $external_id
 * @property string|null $srid
 * @property Carbon $sold_at
 * @property int $quantity
 * @property int $amount_kopecks
 * @property Carbon|null $external_updated_at
 * @property-read SellerAccount $sellerAccount
 * @property-read Product $product
 * @property-read Order|null $order
 */
#[Fillable([
    'seller_account_id',
    'product_id',
    'order_id',
    'external_id',
    'srid',
    'sold_at',
    'quantity',
    'amount_kopecks',
    'external_updated_at',
])]
class Sale extends Model
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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<ReturnFact, $this> */
    public function returnFacts(): HasMany
    {
        return $this->hasMany(ReturnFact::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sold_at' => 'immutable_datetime',
            'quantity' => 'integer',
            'amount_kopecks' => 'integer',
            'external_updated_at' => 'immutable_datetime',
        ];
    }
}
