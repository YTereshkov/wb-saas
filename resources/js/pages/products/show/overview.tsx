import { Head } from '@inertiajs/react';

import { InsightBanner } from '@/components/analytics/insight-banner';
import { KpiStrip } from '@/components/analytics/kpi-strip';
import { MultiLineChart } from '@/components/analytics/multi-line-chart';
import { ProductHeader } from '@/components/products/product-header';
import {
    PrimarySignalPanel,
    SalesFunnel,
    WarehouseTable,
} from '@/components/products/product-panels';
import { AppLayout } from '@/layouts/app-layout';
import type { ProductDetailsData } from '@/types';

export default function ProductOverview({
    details,
}: {
    details: ProductDetailsData;
}) {
    return (
        <>
            <Head title={details.product.title} />
            <AppLayout activeItem="products">
                <div className="mx-auto max-w-[1320px] space-y-5">
                    <ProductHeader
                        product={details.product}
                        active="overview"
                    />
                    <InsightBanner
                        tone={details.signal ? 'warning' : 'success'}
                    >
                        {details.insights.overview}
                    </InsightBanner>
                    <KpiStrip items={details.kpis} />
                    <div className="grid gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(290px,0.7fr)]">
                        <MultiLineChart
                            title="Динамика товара"
                            day={details.series.revenue}
                            points={details.series.revenue}
                            defaultGranularity={details.series.granularity}
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
                        <PrimarySignalPanel details={details} />
                    </div>
                    <div className="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
                        <SalesFunnel funnel={details.funnel} />
                        <WarehouseTable
                            warehouses={details.stock.warehouses}
                            compact
                            detailsHref={`/products/${details.product.id}/stocks`}
                        />
                    </div>
                </div>
            </AppLayout>
        </>
    );
}
