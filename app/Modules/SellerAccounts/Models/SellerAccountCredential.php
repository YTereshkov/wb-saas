<?php

namespace App\Modules\SellerAccounts\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $seller_account_id
 * @property string $token
 * @property string $fingerprint
 * @property list<string>|null $permissions
 * @property Carbon|null $verified_at
 * @property Carbon|null $invalidated_at
 * @property-read SellerAccount $sellerAccount
 */
#[Fillable([
    'seller_account_id',
    'token',
    'fingerprint',
    'permissions',
    'verified_at',
    'invalidated_at',
])]
#[Hidden(['token'])]
class SellerAccountCredential extends Model
{
    /** @return BelongsTo<SellerAccount, $this> */
    public function sellerAccount(): BelongsTo
    {
        return $this->belongsTo(SellerAccount::class);
    }

    public static function fingerprint(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'permissions' => 'array',
            'verified_at' => 'immutable_datetime',
            'invalidated_at' => 'immutable_datetime',
        ];
    }
}
