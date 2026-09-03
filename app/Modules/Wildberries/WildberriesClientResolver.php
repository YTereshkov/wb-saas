<?php

namespace App\Modules\Wildberries;

use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Wildberries\Clients\DemoWildberriesClient;
use App\Modules\Wildberries\Clients\WildberriesHttpClientFactory;
use App\Modules\Wildberries\Contracts\WildberriesClientInterface;

final readonly class WildberriesClientResolver
{
    public function __construct(
        private DemoWildberriesClient $demoClient,
        private WildberriesHttpClientFactory $httpClientFactory,
    ) {}

    public function resolve(SellerAccount $sellerAccount): WildberriesClientInterface
    {
        return match ($sellerAccount->source) {
            SellerAccountSource::Demo => $this->demoClient,
            SellerAccountSource::Wildberries => $this->httpClientFactory->forAccount($sellerAccount),
        };
    }
}
