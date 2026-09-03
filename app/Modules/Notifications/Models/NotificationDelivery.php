<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use App\Modules\Analytics\Models\ProductSignal;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read User $user
 * @property-read ProductSignal|null $productSignal
 */
#[Fillable([
    'user_id', 'seller_account_id', 'product_signal_id', 'event', 'channel', 'status',
    'subject', 'fact', 'recommendation', 'action_url', 'error_code', 'queued_at', 'sent_at',
])]
class NotificationDelivery extends Model
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

    /** @return BelongsTo<ProductSignal, $this> */
    public function productSignal(): BelongsTo
    {
        return $this->belongsTo(ProductSignal::class);
    }

    protected function casts(): array
    {
        return ['queued_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime'];
    }
}
