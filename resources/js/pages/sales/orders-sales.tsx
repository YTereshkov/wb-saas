import { Head } from '@inertiajs/react';

import { InsightBanner } from '@/components/analytics/insight-banner';
import { KpiStrip } from '@/components/analytics/kpi-strip';
import { MultiLineChart } from '@/components/analytics/multi-line-chart';
import { SalesHeader } from '@/components/sales/sales-header';
import { GapTable } from '@/components/sales/sales-product-table';
import { AppLayout } from '@/layouts/app-layout';
import type { SalesAnalyticsData } from '@/types';

export default function OrdersSales({ sales }: { sales: SalesAnalyticsData }) {
    if (sales.state === 'empty') {
        return (
            <>
                <Head title="Продажи — заказы и продажи" />
                <AppLayout activeItem="sales">
                    <div className="mx-auto max-w-[1320px]">
                        <SalesHeader active="orders-sales" />
                        <p className="mt-8 text-sm text-text-secondary">
                            Данные о продажах пока не загружены.
                        </p>
                    </div>
                </AppLayout>
            </>
        );
    }

    const kpis = (sales.kpis ?? []).filter((item) =>
        ['orders', 'sales', 'gap', 'buyout'].includes(item.key),
    );

    return (
        <>
            <Head title="Продажи — заказы и продажи" />
            <AppLayout activeItem="sales">
                <div className="mx-auto max-w-[1320px] space-y-5">
                    <SalesHeader active="orders-sales" />
                    <InsightBanner tone="success">
                        {sales.insights?.ordersSales}
                    </InsightBanner>
                    <KpiStrip items={kpis} />
                    <MultiLineChart
                        title="Заказы и продажи"
                        day={sales.series?.ordersSalesDay ?? []}
                        week={sales.series?.ordersSalesWeek ?? []}
                        points={sales.series?.ordersSalesPoints ?? []}
                        defaultGranularity={sales.series?.granularity}
                        series={[
                            {
                                key: 'orders',
                                label: 'Заказы',
                                color: '#5527ff',
                            },
                            {
                                key: 'sales',
                                label: 'Продажи',
                                color: '#596274',
                                dashed: true,
                            },
                        ]}
                    />
                    <GapTable rows={sales.products?.gap ?? []} />
                </div>
            </AppLayout>
        </>
    );
}
