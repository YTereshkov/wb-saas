<?php

namespace App\Modules\Wildberries\Contracts;

use App\Modules\Wildberries\Data\ImportPeriodData;
use App\Modules\Wildberries\Data\StockData;

interface HistoricalStocksClientInterface
{
    public function createHistoricalStocksReport(string $reportId, ImportPeriodData $period): void;

    public function historicalStocksReportStatus(string $reportId): string;

    public function retryHistoricalStocksReport(string $reportId): void;

    /** @return iterable<StockData> */
    public function downloadHistoricalStocksReport(string $reportId): iterable;
}
