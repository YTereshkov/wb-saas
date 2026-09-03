<?php

namespace App\Modules\SellerAccounts\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TenantResourcePolicy
{
    public function view(User $user, Model $resource): bool
    {
        $sellerAccountId = $resource->getAttribute('seller_account_id');

        return is_int($sellerAccountId)
            && $user->sellerAccounts()->whereKey($sellerAccountId)->exists();
    }

    public function update(User $user, Model $resource): bool
    {
        return $this->view($user, $resource);
    }

    public function delete(User $user, Model $resource): bool
    {
        return $this->view($user, $resource);
    }
}
