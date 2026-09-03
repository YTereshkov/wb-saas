import { Head } from '@inertiajs/react';

import { InsightBanner } from '@/components/analytics/insight-banner';
import { KpiStrip } from '@/components/analytics/kpi-strip';
import { MultiLineChart } from '@/components/analytics/multi-line-chart';
import { SalesHeader } from '@/components/sales/sales-header';
import { ContributionTable } from '@/components/sales/sales-product-table';
import { AppLayout } from '@/layouts/app-layout';
import type { SalesAnalyticsData } from '@/types';

export default function SalesDynamics({
    sales,
}: {
    sales: SalesAnalyticsData;
}) {
    if (sales.state === 'empty') {
        return (
            <>
                <Head title="Продажи — динамика" />
                <AppLayout activeItem="sales">
                    <div className="mx-auto max-w-[1320px]">
                        <SalesHeader active="dynamics" />
                        <p className="mt-8 text-sm text-text-secondary">
                            Данные о продажах пока не загружены.
                        </p>
                    </div>
                </AppLayout>
            </>
        );
    }

    const kpis = (sales.kpis ?? []).filter((item) =>
        ['revenue', 'orders', 'sales', 'buyout'].includes(item.key),
    );

    return (
        <>
            <Head title="Продажи — динамика" />
            <AppLayout activeItem="sales">
                <div className="mx-auto max-w-[1320px] space-y-5">
                    <SalesHeader active="dynamics" />
                    <InsightBanner>{sales.insights?.dynamics}</InsightBanner>
                    <KpiStrip items={kpis} />
                    <MultiLineChart
                        title="Динамика выручки"
                        day={sales.series?.revenueDay ?? []}
                        week={sales.series?.revenueWeek ?? []}
                        points={sales.series?.revenuePoints ?? []}
                        defaultGranularity={sales.series?.granularity}
                        series={[
                            {
                                key: 'current',
                                label: 'Текущий период',
                                color: '#5527ff',
                            },
                            {
                                key: 'comparison',
                                label: 'Прошлый период',
                                color: '#9ca3b0',
                                dashed: true,
                            },
                        ]}
                        format="money"
                    />
                    <ContributionTable
                        rows={sales.products?.contribution ?? []}
                    />
                </div>
            </AppLayout>
        </>
    );
}
