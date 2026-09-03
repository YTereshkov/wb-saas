<?php

namespace App\Modules\Synchronization\Enums;

enum SyncResourceStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
