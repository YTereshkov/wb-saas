<?php

namespace App\Modules\SellerAccounts\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $active_seller_account_id
 * @property string $period_preset
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property Carbon|null $comparison_start
 * @property Carbon|null $comparison_end
 * @property string $granularity
 * @property-read User $user
 * @property-read SellerAccount|null $activeSellerAccount
 */
#[Fillable([
    'user_id',
    'active_seller_account_id',
    'period_preset',
    'period_start',
    'period_end',
    'comparison_start',
    'comparison_end',
    'granularity',
])]
class UserPreference extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<SellerAccount, $this> */
    public function activeSellerAccount(): BelongsTo
    {
        return $this->belongsTo(SellerAccount::class, 'active_seller_account_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'comparison_start' => 'immutable_date',
            'comparison_end' => 'immutable_date',
        ];
    }
}
