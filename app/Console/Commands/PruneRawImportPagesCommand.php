<?php

namespace App\Console\Commands;

use App\Modules\Synchronization\Actions\PruneRawImportPages;
use Illuminate\Console\Command;

final class PruneRawImportPagesCommand extends Command
{
    protected $signature = 'sellerscope:prune-raw-imports';

    protected $description = 'Delete expired raw Wildberries import pages';

    public function handle(PruneRawImportPages $action): int
    {
        $count = $action->handle();
        $this->info("Pruned raw import pages: {$count}");

        return self::SUCCESS;
    }
}
