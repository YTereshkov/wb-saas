<?php

namespace App\Modules\Analytics\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property array<string, mixed> $evidence */
#[Fillable([
    'seller_account_id',
    'product_id',
    'type',
    'priority',
    'severity',
    'evidence',
    'fact',
    'risk',
    'recommendation',
    'calculated_at',
    'data_revision',
])]
class ProductSignal extends Model
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
            'priority' => 'integer',
            'evidence' => 'array',
            'calculated_at' => 'immutable_datetime',
            'data_revision' => 'integer',
        ];
    }
}
