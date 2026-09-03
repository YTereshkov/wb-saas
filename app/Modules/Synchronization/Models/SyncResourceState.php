<?php

namespace App\Modules\Synchronization\Models;

use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Enums\ResourceAvailability;
use App\Modules\Synchronization\Enums\SyncResource;
use App\Modules\Synchronization\Enums\SyncResourceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $seller_account_id
 * @property SyncResource $resource
 * @property SyncResourceStatus $status
 * @property ResourceAvailability $availability
 * @property string|null $cursor
 * @property string|null $checkpoint
 * @property int $processed_pages
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $last_success_at
 * @property string|null $error_code
 */
#[Fillable([
    'seller_account_id', 'resource', 'status', 'availability', 'cursor', 'checkpoint',
    'processed_pages', 'last_attempt_at', 'last_success_at', 'error_code',
])]
class SyncResourceState extends Model
{
    /** @return BelongsTo<SellerAccount, $this> */
    public function sellerAccount(): BelongsTo
    {
        return $this->belongsTo(SellerAccount::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'resource' => SyncResource::class,
            'status' => SyncResourceStatus::class,
            'availability' => ResourceAvailability::class,
            'processed_pages' => 'integer',
            'last_attempt_at' => 'immutable_datetime',
            'last_success_at' => 'immutable_datetime',
        ];
    }
}
