<?php

namespace App\Modules\Synchronization\Enums;

enum SyncRunType: string
{
    case Initial = 'initial';
    case Incremental = 'incremental';
}
