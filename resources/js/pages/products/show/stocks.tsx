import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import { InsightBanner } from '@/components/analytics/insight-banner';
import { MultiLineChart } from '@/components/analytics/multi-line-chart';
import { ProductHeader } from '@/components/products/product-header';
import {
    StockMetricStrip,
    WarehouseTable,
} from '@/components/products/product-panels';
import { AppLayout } from '@/layouts/app-layout';
import type { ProductDetailsData } from '@/types';

export default function ProductStocks({
    details,
}: {
    details: ProductDetailsData;
}) {
    return (
        <>
            <Head title={`${details.product.title} — остатки`} />
            <AppLayout activeItem="products" dataResource="stocks">
                <div className="mx-auto max-w-[1320px] space-y-5">
                    <ProductHeader product={details.product} active="stocks" />
                    <InsightBanner tone="warning">
                        {details.insights.stocks}
                    </InsightBanner>
                    <StockMetricStrip stock={details.stock} />
                    <div className="grid gap-5 xl:grid-cols-[1.55fr_0.65fr]">
                        <MultiLineChart
                            title="Остаток и прогноз"
                            day={details.stock.series}
                            series={[
                                {
                                    key: 'actual',
                                    label: 'Фактический остаток',
                                    color: '#5527ff',
                                },
                                {
                                    key: 'forecast',
                                    label: 'Прогноз',
                                    color: '#ef6b44',
                                    dashed: true,
                                },
                            ]}
                        />
                        <section className="rounded-lg border bg-background p-5 sm:p-6">
                            <h2 className="text-base font-semibold">
                                Рекомендуемая поставка
                            </h2>
                            <p className="mt-5 text-[30px] font-semibold text-primary">
                                {details.stock.recommendedSupply} шт.
                            </p>
                            <p className="mt-2 text-sm text-text-secondary">
                                Базовый объём на 30 дней
                            </p>
                            <p className="mt-5 border-t pt-4 text-xs leading-5 text-text-secondary">
                                {details.stock.averageDailySales} шт./день × 30
                                дней − {details.stock.total} шт. в наличии
                            </p>
                            <Link
                                href="/stocks"
                                className="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-primary"
                            >
                                Перейти к поставкам{' '}
                                <ArrowRight className="size-4" />
                            </Link>
                        </section>
                    </div>
                    <WarehouseTable warehouses={details.stock.warehouses} />
                </div>
            </AppLayout>
        </>
    );
}
