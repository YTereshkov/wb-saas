<?php

namespace App\Modules\Synchronization\Models;

use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Enums\SyncRunStatus;
use App\Modules\Synchronization\Enums\SyncRunType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $seller_account_id
 * @property SyncRunType $type
 * @property SyncRunStatus $status
 * @property int $progress
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property string|null $error_code
 * @property string|null $error_summary
 */
#[Fillable([
    'seller_account_id', 'type', 'status', 'progress', 'started_at', 'finished_at',
    'error_code', 'error_summary',
])]
class SyncRun extends Model
{
    /** @return BelongsTo<SellerAccount, $this> */
    public function sellerAccount(): BelongsTo
    {
        return $this->belongsTo(SellerAccount::class);
    }

    /** @return HasMany<ImportBatch, $this> */
    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => SyncRunType::class,
            'status' => SyncRunStatus::class,
            'progress' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
