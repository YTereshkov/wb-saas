<?php

namespace App\Console\Commands;

use App\Modules\Synchronization\Actions\DispatchDueSellerAccountSyncs;
use Illuminate\Console\Command;

final class DispatchDueSellerAccountSyncsCommand extends Command
{
    protected $signature = 'sellerscope:sync-due';

    protected $description = 'Dispatch synchronization for due Wildberries seller accounts';

    public function handle(DispatchDueSellerAccountSyncs $action): int
    {
        $count = $action->handle();
        $this->info("Dispatched seller account synchronizations: {$count}");

        return self::SUCCESS;
    }
}
