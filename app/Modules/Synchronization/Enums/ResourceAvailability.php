<?php

namespace App\Modules\Synchronization\Enums;

enum ResourceAvailability: string
{
    case Unknown = 'unknown';
    case Available = 'available';
    case Unavailable = 'unavailable';
}
