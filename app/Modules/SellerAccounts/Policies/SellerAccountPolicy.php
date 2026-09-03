<?php

namespace App\Modules\SellerAccounts\Policies;

use App\Models\User;
use App\Modules\SellerAccounts\Models\SellerAccount;

class SellerAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SellerAccount $sellerAccount): bool
    {
        return $sellerAccount->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function update(User $user, SellerAccount $sellerAccount): bool
    {
        return $this->view($user, $sellerAccount);
    }

    public function delete(User $user, SellerAccount $sellerAccount): bool
    {
        return $this->view($user, $sellerAccount);
    }
}
