<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool $enabled
 * @property int|null $threshold
 * @property string|null $frequency
 */
#[Fillable(['user_id', 'seller_account_id', 'event', 'channel', 'enabled', 'threshold', 'frequency'])]
class NotificationPreference extends Model
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

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'threshold' => 'integer'];
    }
}
