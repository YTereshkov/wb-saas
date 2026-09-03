import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Lightbulb, Plus } from 'lucide-react';

import { AttentionPanel } from '@/components/analytics/attention-panel';
import { KpiStrip } from '@/components/analytics/kpi-strip';
import { OverviewProductTable } from '@/components/analytics/overview-product-table';
import { RevenueChart } from '@/components/analytics/revenue-chart';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { OverviewData } from '@/types';

export default function OverviewIndex({
    overview,
}: {
    overview: OverviewData;
}) {
    if (overview.state === 'empty') {
        return (
            <>
                <Head title="Обзор" />
                <AppLayout activeItem="overview">
                    <div className="mx-auto max-w-[1320px]">
                        <h1 className="text-[30px] leading-[38px] font-semibold tracking-normal">
                            Обзор
                        </h1>
                        <div className="mt-6 flex min-h-[420px] flex-col items-center justify-center rounded-lg border border-dashed border-border bg-surface-subtle px-6 text-center">
                            <span className="grid size-12 place-items-center rounded-full bg-accent text-primary">
                                <Plus />
                            </span>
                            <h2 className="mt-4 text-lg font-semibold">
                                Подключите кабинет Wildberries
                            </h2>
                            <p className="mt-2 max-w-md text-sm text-text-secondary">
                                После первой загрузки здесь появятся показатели,
                                график и главные сигналы.
                            </p>
                            <Button asChild className="mt-5">
                                <Link href="/settings/cabinets/connect">
                                    Подключить кабинет
                                </Link>
                            </Button>
                        </div>
                    </div>
                </AppLayout>
            </>
        );
    }

    return (
        <>
            <Head title="Обзор" />
            <AppLayout activeItem="overview">
                <div className="mx-auto max-w-[1320px] space-y-5">
                    <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
                        <div>
                            <h1 className="text-[30px] leading-[38px] font-semibold tracking-normal">
                                Обзор
                            </h1>
                            <p className="mt-1 text-sm text-text-secondary">
                                Главное по кабинету за выбранный период
                            </p>
                        </div>
                        <Link
                            href="/products/attention"
                            className="inline-flex items-center gap-2 text-sm font-semibold text-primary"
                        >
                            Перейти к сигналам <ArrowRight className="size-4" />
                        </Link>
                    </div>

                    <div className="flex items-start gap-3 rounded-lg bg-accent px-4 py-3.5 text-sm text-accent-foreground sm:px-5">
                        <Lightbulb
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <p>
                            <span className="font-semibold">Коротко:</span>{' '}
                            {overview.insight}
                        </p>
                    </div>

                    <KpiStrip items={overview.kpis ?? []} />

                    <div className="grid gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(300px,0.8fr)]">
                        <RevenueChart
                            day={overview.series?.day ?? []}
                            week={overview.series?.week ?? []}
                            points={overview.series?.points ?? []}
                            defaultGranularity={
                                overview.series?.granularity ?? 'day'
                            }
                        />
                        <AttentionPanel
                            attention={
                                overview.attention ?? { count: 0, signals: [] }
                            }
                        />
                    </div>

                    <OverviewProductTable
                        products={
                            overview.products ?? {
                                leaders: [],
                                decline: [],
                                risk: [],
                            }
                        }
                    />
                </div>
            </AppLayout>
        </>
    );
}
