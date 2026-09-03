<?php

namespace App\Modules\SellerAccounts\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $filters
 * @property list<string>|null $columns
 * @property string|null $sort_column
 * @property string|null $sort_direction
 * @property int|null $page_size
 */
#[Fillable([
    'user_id',
    'seller_account_id',
    'section',
    'view_key',
    'filters',
    'columns',
    'sort_column',
    'sort_direction',
    'page_size',
])]
class SavedView extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<SellerAccount, $this> */
    public function sellerAccount(): BelongsTo
    {
        return $this->belongsTo(SellerAccount::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'columns' => 'array',
            'page_size' => 'integer',
        ];
    }
}
