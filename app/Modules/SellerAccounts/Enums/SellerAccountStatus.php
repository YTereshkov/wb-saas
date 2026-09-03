<?php

namespace App\Modules\SellerAccounts\Enums;

enum SellerAccountStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case InitialSync = 'initial_sync';
    case Active = 'active';
    case Partial = 'partial';
    case InvalidCredentials = 'invalid_credentials';
    case Disconnected = 'disconnected';
}
