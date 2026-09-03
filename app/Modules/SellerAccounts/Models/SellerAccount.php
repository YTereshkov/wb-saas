<?php

namespace App\Modules\SellerAccounts\Models;

use App\Models\User;
use App\Modules\Analytics\Models\AccountDailyMetric;
use App\Modules\Analytics\Models\ProductDailyMetric;
use App\Modules\Analytics\Models\ProductSignal;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockSnapshot;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\ReturnFact;
use App\Modules\Sales\Models\Sale;
use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\Synchronization\Models\ImportBatch;
use App\Modules\Synchronization\Models\RawImportPage;
use App\Modules\Synchronization\Models\SyncResourceState;
use App\Modules\Synchronization\Models\SyncRun;
use Database\Factories\SellerAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $wb_account_id
 * @property SellerAccountSource $source
 * @property SellerAccountStatus $status
 * @property string $timezone
 * @property int $data_revision
 * @property Carbon|null $last_sync_started_at
 * @property Carbon|null $last_sync_completed_at
 * @property Carbon|null $disconnected_at
 * @property-read User $user
 * @property-read SellerAccountCredential|null $credential
 */
#[Fillable([
    'user_id',
    'name',
    'wb_account_id',
    'source',
    'status',
    'timezone',
    'data_revision',
    'last_sync_started_at',
    'last_sync_completed_at',
    'disconnected_at',
])]
#[UseFactory(SellerAccountFactory::class)]
class SellerAccount extends Model
{
    /** @use HasFactory<SellerAccountFactory> */
    use HasFactory;

    public function environment(): string
    {
        if ($this->source === SellerAccountSource::Demo) {
            return 'demo';
        }

        return is_string($this->wb_account_id) && str_starts_with($this->wb_account_id, 'sandbox:')
            ? 'sandbox'
            : 'production';
    }

    public function displayWbAccountId(): ?string
    {
        if ($this->wb_account_id === null) {
            return null;
        }

        return $this->environment() === 'sandbox'
            ? substr($this->wb_account_id, strlen('sandbox:'))
            : $this->wb_account_id;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<SellerAccountCredential, $this> */
    public function credential(): HasOne
    {
        return $this->hasOne(SellerAccountCredential::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<Warehouse, $this> */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return HasMany<ReturnFact, $this> */
    public function returnFacts(): HasMany
    {
        return $this->hasMany(ReturnFact::class);
    }

    /** @return HasMany<StockSnapshot, $this> */
    public function stockSnapshots(): HasMany
    {
        return $this->hasMany(StockSnapshot::class);
    }

    /** @return HasMany<SyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    /** @return HasMany<SyncResourceState, $this> */
    public function syncResourceStates(): HasMany
    {
        return $this->hasMany(SyncResourceState::class);
    }

    /** @return HasMany<ImportBatch, $this> */
    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }

    /** @return HasMany<RawImportPage, $this> */
    public function rawImportPages(): HasMany
    {
        return $this->hasMany(RawImportPage::class);
    }

    /** @return HasMany<AccountDailyMetric, $this> */
    public function accountDailyMetrics(): HasMany
    {
        return $this->hasMany(AccountDailyMetric::class);
    }

    /** @return HasMany<ProductDailyMetric, $this> */
    public function productDailyMetrics(): HasMany
    {
        return $this->hasMany(ProductDailyMetric::class);
    }

    /** @return HasMany<ProductSignal, $this> */
    public function productSignals(): HasMany
    {
        return $this->hasMany(ProductSignal::class);
    }

    /** @return HasMany<NotificationPreference, $this> */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /** @return HasMany<NotificationDelivery, $this> */
    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    /** @return HasMany<SavedView, $this> */
    public function savedViews(): HasMany
    {
        return $this->hasMany(SavedView::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source' => SellerAccountSource::class,
            'status' => SellerAccountStatus::class,
            'data_revision' => 'integer',
            'last_sync_started_at' => 'immutable_datetime',
            'last_sync_completed_at' => 'immutable_datetime',
            'disconnected_at' => 'immutable_datetime',
        ];
    }
}
