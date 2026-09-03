<?php

namespace App\Modules\Synchronization\Actions;

use App\Modules\Synchronization\Models\RawImportPage;
use Carbon\CarbonImmutable;

final class PruneRawImportPages
{
    public function handle(?CarbonImmutable $now = null): int
    {
        return RawImportPage::query()
            ->where('expires_at', '<=', $now ?? CarbonImmutable::now('UTC'))
            ->delete();
    }
}
