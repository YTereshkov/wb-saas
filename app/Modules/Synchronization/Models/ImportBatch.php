<?php

namespace App\Modules\Synchronization\Models;

use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Enums\SyncResource;
use App\Modules\Synchronization\Enums\SyncResourceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'seller_account_id', 'sync_run_id', 'source', 'resource', 'status',
    'period_start', 'period_end', 'cursor_in', 'cursor_out', 'page_key',
    'checksum', 'imported_count', 'started_at', 'finished_at', 'error_code',
])]
class ImportBatch extends Model
{
    /** @return BelongsTo<SellerAccount, $this> */
    public function sellerAccount(): BelongsTo
    {
        return $this->belongsTo(SellerAccount::class);
    }

    /** @return BelongsTo<SyncRun, $this> */
    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }

    /** @return HasOne<RawImportPage, $this> */
    public function rawPage(): HasOne
    {
        return $this->hasOne(RawImportPage::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source' => SellerAccountSource::class,
            'resource' => SyncResource::class,
            'status' => SyncResourceStatus::class,
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'imported_count' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
