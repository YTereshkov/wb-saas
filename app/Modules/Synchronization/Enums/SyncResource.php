<?php

namespace App\Modules\Synchronization\Enums;

enum SyncResource: string
{
    case Products = 'products';
    case Orders = 'orders';
    case Sales = 'sales';
    case Stocks = 'stocks';
    case StockHistory = 'stock_history';
}
