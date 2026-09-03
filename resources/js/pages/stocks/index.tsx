import { Head } from '@inertiajs/react';

import { StockHeader } from '@/components/stocks/stock-header';
import { StockTable } from '@/components/stocks/stock-table';
import { AppLayout } from '@/layouts/app-layout';
import type { StockAnalyticsData } from '@/types';

export default function StocksIndex({
    stocks,
}: {
    stocks: StockAnalyticsData;
}) {
    const ready =
        stocks.state === 'ready' &&
        stocks.counts &&
        stocks.insight &&
        stocks.rows &&
        stocks.pagination &&
        stocks.filters &&
        stocks.categories &&
        stocks.warehouses;

    return (
        <>
            <Head title="Остатки" />
            <AppLayout activeItem="stocks">
                <div className="mx-auto max-w-[1320px] space-y-5">
                    {ready ? (
                        <>
                            <StockHeader
                                stocks={{
                                    ...stocks,
                                    counts: stocks.counts!,
                                    insight: stocks.insight!,
                                }}
                            />
                            <StockTable
                                stocks={{
                                    ...stocks,
                                    rows: stocks.rows!,
                                    pagination: stocks.pagination!,
                                    filters: stocks.filters!,
                                    categories: stocks.categories!,
                                    warehouses: stocks.warehouses!,
                                }}
                            />
                        </>
                    ) : (
                        <>
                            <h1 className="text-[30px] font-semibold">
                                Остатки
                            </h1>
                            <p className="py-12 text-center text-sm text-text-secondary">
                                {stocks.state === 'unavailable'
                                    ? 'За выбранный период SellerScope не имеет снимков остатков.'
                                    : 'Данные об остатках появятся после синхронизации.'}
                            </p>
                        </>
                    )}
                </div>
            </AppLayout>
        </>
    );
}
