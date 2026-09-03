<?php

namespace App\Modules\Wildberries\Clients;

use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\SellerAccounts\Models\SellerAccountCredential;
use App\Modules\Wildberries\Contracts\WildberriesClientInterface;
use App\Modules\Wildberries\Normalizers\HistoricalStockCsvNormalizer;
use App\Modules\Wildberries\Normalizers\OrderNormalizer;
use App\Modules\Wildberries\Normalizers\ProductNormalizer;
use App\Modules\Wildberries\Normalizers\SalesNormalizer;
use App\Modules\Wildberries\Normalizers\StockNormalizer;
use App\Modules\Wildberries\Support\WildberriesTokenInspector;
use DomainException;

final readonly class WildberriesHttpClientFactory
{
    public function __construct(
        private ProductNormalizer $productNormalizer,
        private OrderNormalizer $orderNormalizer,
        private SalesNormalizer $salesNormalizer,
        private StockNormalizer $stockNormalizer,
        private HistoricalStockCsvNormalizer $historicalStockCsvNormalizer,
        private WildberriesTokenInspector $tokenInspector,
    ) {}

    public function forAccount(SellerAccount $account): WildberriesClientInterface
    {
        $account->loadMissing('credential');
        $credential = $account->credential;
        if (! $credential instanceof SellerAccountCredential || $credential->invalidated_at !== null) {
            throw new DomainException('Seller account has no valid Wildberries credential.');
        }

        return $this->tokenInspector->isTest($credential->token)
            ? $this->forSandboxToken($credential->token, $account->timezone)
            : $this->forToken($credential->token, $account->timezone);
    }

    public function forToken(string $token, string $timezone = 'Europe/Moscow'): WildberriesHttpClient
    {
        return new WildberriesHttpClient(
            $token,
            $this->productNormalizer,
            $this->orderNormalizer,
            $this->salesNormalizer,
            $this->stockNormalizer,
            $this->historicalStockCsvNormalizer,
            $this->tokenInspector,
            $timezone,
        );
    }

    public function forSandboxToken(string $token, string $timezone = 'Europe/Moscow'): WildberriesSandboxClient
    {
        return new WildberriesSandboxClient(
            $token,
            $this->productNormalizer,
            $this->orderNormalizer,
            $this->salesNormalizer,
            $this->stockNormalizer,
            $this->historicalStockCsvNormalizer,
            $this->tokenInspector,
            $timezone,
        );
    }
}
