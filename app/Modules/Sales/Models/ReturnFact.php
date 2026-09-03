<?php

namespace App\Modules\Sales\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $seller_account_id
 * @property int $product_id
 * @property int|null $sale_id
 * @property string $external_id
 * @property Carbon $returned_at
 * @property int $quantity
 * @property int $amount_kopecks
 * @property Carbon|null $external_updated_at
 * @property-read SellerAccount $sellerAccount
 * @property-read Product $product
 * @property-read Sale|null $sale
 */
#[Fillable([
    'seller_account_id',
    'product_id',
    'sale_id',
    'external_id',
    'returned_at',
    'quantity',
    'amount_kopecks',
    'external_updated_at',
])]
class ReturnFact extends Model
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

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'returned_at' => 'immutable_datetime',
            'quantity' => 'integer',
            'amount_kopecks' => 'integer',
            'external_updated_at' => 'immutable_datetime',
        ];
    }
}
