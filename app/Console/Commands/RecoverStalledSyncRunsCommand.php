<?php

namespace App\Console\Commands;

use App\Modules\Synchronization\Actions\RecoverStalledSyncRuns;
use Illuminate\Console\Command;

final class RecoverStalledSyncRunsCommand extends Command
{
    protected $signature = 'sellerscope:recover-stalled-syncs';

    protected $description = 'Mark stalled synchronization runs for safe retry';

    public function handle(RecoverStalledSyncRuns $action): int
    {
        $count = $action->handle();
        $this->info("Recovered stalled synchronization runs: {$count}");

        return self::SUCCESS;
    }
}
