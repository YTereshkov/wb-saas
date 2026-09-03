import { Head } from '@inertiajs/react';

import { InsightBanner } from '@/components/analytics/insight-banner';
import { KpiStrip } from '@/components/analytics/kpi-strip';
import { SalesHeader } from '@/components/sales/sales-header';
import { SalesQualityChart } from '@/components/sales/sales-quality-chart';
import {
    QualityAttention,
    SalesQualityTable,
} from '@/components/sales/sales-quality-table';
import { AppLayout } from '@/layouts/app-layout';
import type { SalesAnalyticsData } from '@/types';

export default function BuyoutReturns({
    sales,
}: {
    sales: SalesAnalyticsData;
}) {
    if (sales.state === 'empty') {
        return (
            <>
                <Head title="Продажи — выкуп и возвраты" />
                <AppLayout activeItem="sales">
                    <div className="mx-auto max-w-[1320px]">
                        <SalesHeader active="buyout-returns" />
                        <p className="mt-8 text-sm text-text-secondary">
                            Данные о продажах пока не загружены.
                        </p>
                    </div>
                </AppLayout>
            </>
        );
    }

    const kpis = (sales.kpis ?? [])
        .filter((item) =>
            ['buyout', 'gap', 'returns-rate', 'returned'].includes(item.key),
        )
        .map((item) =>
            item.key === 'gap' ? { ...item, label: 'Не выкуплено' } : item,
        );
    const quality = sales.products?.quality ?? [];

    return (
        <>
            <Head title="Продажи — выкуп и возвраты" />
            <AppLayout activeItem="sales">
                <div className="mx-auto max-w-[1320px] space-y-5">
                    <SalesHeader active="buyout-returns" />
                    {sales.insights?.buyoutReturns && (
                        <InsightBanner tone="warning">
                            {sales.insights.buyoutReturns}
                        </InsightBanner>
                    )}
                    <KpiStrip items={kpis} />
                    <div className="grid gap-5 xl:grid-cols-[minmax(0,1.7fr)_390px]">
                        <SalesQualityChart
                            day={sales.series?.qualityDay ?? []}
                            week={sales.series?.qualityWeek ?? []}
                            points={sales.series?.qualityPoints ?? []}
                            defaultGranularity={sales.series?.granularity}
                        />
                        <QualityAttention rows={quality} />
                    </div>
                    <SalesQualityTable rows={quality} />
                </div>
            </AppLayout>
        </>
    );
}
