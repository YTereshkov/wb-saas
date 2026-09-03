<?php

namespace App\Modules\Inventory\Models;

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
 * @property int $warehouse_id
 * @property string $variant_external_id
 * @property Carbon $snapshot_at
 * @property Carbon $snapshot_date
 * @property int $quantity
 * @property-read SellerAccount $sellerAccount
 * @property-read Product $product
 * @property-read Warehouse $warehouse
 */
#[Fillable([
    'seller_account_id',
    'product_id',
    'warehouse_id',
    'variant_external_id',
    'snapshot_at',
    'snapshot_date',
    'quantity',
])]
class StockSnapshot extends Model
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

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'snapshot_at' => 'immutable_datetime',
            'snapshot_date' => 'immutable_date',
            'quantity' => 'integer',
        ];
    }
}
