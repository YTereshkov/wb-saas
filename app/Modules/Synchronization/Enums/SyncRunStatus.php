<?php

namespace App\Modules\Synchronization\Enums;

enum SyncRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
