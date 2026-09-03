import { Head } from '@inertiajs/react';

import { InsightBanner } from '@/components/analytics/insight-banner';
import { KpiStrip } from '@/components/analytics/kpi-strip';
import { SalesHeader } from '@/components/sales/sales-header';
import { SalesResultsTable } from '@/components/sales/sales-results-table';
import { AppLayout } from '@/layouts/app-layout';
import type { SalesAnalyticsData, SalesProductTableData } from '@/types';

export default function SalesProducts({
    sales,
    table,
}: {
    sales: SalesAnalyticsData;
    table: SalesProductTableData;
}) {
    const ready =
        table.state === 'ready' &&
        table.rows &&
        table.pagination &&
        table.filters &&
        table.categories;

    return (
        <>
            <Head title="Продажи — по товарам" />
            <AppLayout activeItem="sales">
                <div className="mx-auto max-w-[1320px] space-y-5">
                    <SalesHeader active="products" />
                    {sales.state === 'ready' && (
                        <>
                            <InsightBanner>
                                {sales.insights?.products}
                            </InsightBanner>
                            <KpiStrip
                                items={(sales.kpis ?? []).filter((item) =>
                                    [
                                        'revenue',
                                        'sales',
                                        'average-sale',
                                        'decline-count',
                                    ].includes(item.key),
                                )}
                            />
                        </>
                    )}
                    {ready ? (
                        <SalesResultsTable
                            table={{
                                ...table,
                                rows: table.rows!,
                                pagination: table.pagination!,
                                filters: table.filters!,
                                categories: table.categories!,
                            }}
                        />
                    ) : (
                        <p className="py-12 text-center text-sm text-text-secondary">
                            Данные по товарам пока не загружены.
                        </p>
                    )}
                </div>
            </AppLayout>
        </>
    );
}
