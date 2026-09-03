import { Head } from '@inertiajs/react';

import { InsightBanner } from '@/components/analytics/insight-banner';
import { KpiStrip } from '@/components/analytics/kpi-strip';
import { MultiLineChart } from '@/components/analytics/multi-line-chart';
import { ProductHeader } from '@/components/products/product-header';
import { SalesFunnel } from '@/components/products/product-panels';
import { AppLayout } from '@/layouts/app-layout';
import type { ProductDetailsData } from '@/types';

export default function ProductSales({
    details,
}: {
    details: ProductDetailsData;
}) {
    return (
        <>
            <Head title={`${details.product.title} — продажи`} />
            <AppLayout activeItem="products">
                <div className="mx-auto max-w-[1320px] space-y-5">
                    <ProductHeader product={details.product} active="sales" />
                    <InsightBanner tone="success">
                        {details.insights.sales}
                    </InsightBanner>
                    <KpiStrip items={details.salesKpis} />
                    <div className="grid gap-5 xl:grid-cols-[1.45fr_0.75fr]">
                        <MultiLineChart
                            title="Заказы и продажи"
                            day={details.series.sales}
                            points={details.series.sales}
                            defaultGranularity={details.series.granularity}
                            series={[
                                {
                                    key: 'orders',
                                    label: 'Заказы',
                                    color: '#5527ff',
                                },
                                {
                                    key: 'sales',
                                    label: 'Продажи',
                                    color: '#51596a',
                                },
                            ]}
                        />
                        <SalesFunnel funnel={details.funnel} compact />
                    </div>
                    <section className="overflow-hidden rounded-lg border bg-background">
                        <div className="px-5 py-4">
                            <h2 className="text-base font-semibold">
                                {details.series.granularity === 'month'
                                    ? 'Динамика по месяцам'
                                    : 'Динамика по неделям'}
                            </h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[680px] text-sm">
                                <thead className="border-y bg-surface-subtle text-xs text-text-secondary">
                                    <tr>
                                        <th className="px-5 py-2.5 text-left font-medium">
                                            Период
                                        </th>
                                        <th className="px-5 text-right font-medium">
                                            Заказы
                                        </th>
                                        <th className="px-5 text-right font-medium">
                                            Продажи
                                        </th>
                                        <th className="px-5 text-right font-medium">
                                            Выкуп
                                        </th>
                                        <th className="px-5 text-right font-medium">
                                            Возвраты
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {details.weekly.map((week) => (
                                        <tr
                                            key={week.label}
                                            className="border-b last:border-0"
                                        >
                                            <td className="px-5 py-3 font-medium">
                                                {week.label}
                                            </td>
                                            <td className="px-5 text-right">
                                                {week.orders}
                                            </td>
                                            <td className="px-5 text-right">
                                                {week.sales}
                                            </td>
                                            <td className="px-5 text-right">
                                                {week.buyout === null
                                                    ? '—'
                                                    : `${week.buyout}%`}
                                            </td>
                                            <td className="px-5 text-right">
                                                {week.returnsRate === null
                                                    ? '—'
                                                    : `${week.returnsRate}%`}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </AppLayout>
        </>
    );
}
