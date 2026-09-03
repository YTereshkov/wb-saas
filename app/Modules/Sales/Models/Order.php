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
 * @property string $srid
 * @property Carbon $ordered_at
 * @property int $quantity
 * @property int $amount_kopecks
 * @property string $status
 * @property bool $is_cancelled
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $external_updated_at
 * @property-read SellerAccount $sellerAccount
 * @property-read Product $product
 */
#[Fillable([
    'seller_account_id',
    'product_id',
    'srid',
    'ordered_at',
    'quantity',
    'amount_kopecks',
    'status',
    'is_cancelled',
    'cancelled_at',
    'external_updated_at',
])]
class Order extends Model
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

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ordered_at' => 'immutable_datetime',
            'quantity' => 'integer',
            'amount_kopecks' => 'integer',
            'is_cancelled' => 'boolean',
            'cancelled_at' => 'immutable_datetime',
            'external_updated_at' => 'immutable_datetime',
        ];
    }
}
