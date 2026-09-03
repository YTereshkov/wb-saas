<?php

namespace App\Modules\Catalog\Models;

use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $seller_account_id
 * @property string $external_id
 * @property string $name
 * @property-read SellerAccount $sellerAccount
 */
#[Fillable(['seller_account_id', 'external_id', 'name'])]
class Category extends Model
{
    /** @return BelongsTo<SellerAccount, $this> */
    public function sellerAccount(): BelongsTo
    {
        return $this->belongsTo(SellerAccount::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
