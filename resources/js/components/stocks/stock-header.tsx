import { Link } from '@inertiajs/react';
import { CircleAlert, Info } from 'lucide-react';
import { useEffect, useRef } from 'react';

import { cn } from '@/lib/utils';
import type { StockAnalyticsData, StockView } from '@/types';

const tabs: Array<{
    key: StockView;
    label: string;
    href: string;
    count: keyof NonNullable<StockAnalyticsData['counts']>;
}> = [
    { key: 'all', label: 'Все', href: '/stocks', count: 'all' },
    {
        key: 'supply',
        label: 'Нужна поставка',
        href: '/stocks/supply',
        count: 'supply',
    },
    {
        key: 'out_of_stock',
        label: 'Закончились',
        href: '/stocks/out-of-stock',
        count: 'outOfStock',
    },
    {
        key: 'no_movement',
        label: 'Без движения',
        href: '/stocks/no-movement',
        count: 'noMovement',
    },
];

export function StockHeader({
    stocks,
}: {
    stocks: StockAnalyticsData & {
        counts: NonNullable<StockAnalyticsData['counts']>;
        insight: NonNullable<StockAnalyticsData['insight']>;
    };
}) {
    const tabsRef = useRef<HTMLElement>(null);
    const Icon = stocks.insight.tone === 'info' ? Info : CircleAlert;

    useEffect(() => {
        tabsRef.current
            ?.querySelector('[aria-current="page"]')
            ?.scrollIntoView({ block: 'nearest', inline: 'center' });
    }, [stocks.view]);

    return (
        <>
            <header>
                <h1 className="text-[30px] leading-[38px] font-semibold">
                    Остатки
                </h1>
                <p className="mt-1 text-sm text-text-secondary">
                    Текущие остатки и прогноз на основе продаж за выбранный
                    период
                </p>
            </header>
            <section className="flex items-start gap-3 py-1">
                <span
                    className={cn(
                        'grid size-10 shrink-0 place-items-center rounded-full',
                        stocks.insight.tone === 'danger'
                            ? 'bg-danger-soft text-destructive'
                            : stocks.insight.tone === 'warning'
                              ? 'bg-warning-soft text-warning'
                              : 'bg-secondary text-primary',
                    )}
                >
                    <Icon className="size-5" />
                </span>
                <div>
                    <p className="font-semibold">{stocks.insight.title}</p>
                    <p className="mt-1 text-sm text-text-secondary">
                        {stocks.insight.detail}
                    </p>
                </div>
            </section>
            <nav
                ref={tabsRef}
                className="flex gap-7 overflow-x-auto border-b"
                aria-label="Разделы остатков"
            >
                {tabs.map((tab) => (
                    <Link
                        key={tab.key}
                        href={tab.href}
                        aria-current={
                            stocks.view === tab.key ? 'page' : undefined
                        }
                        className={cn(
                            'shrink-0 border-b-2 px-1 pb-3 text-sm font-medium',
                            stocks.view === tab.key
                                ? 'border-primary text-primary'
                                : 'border-transparent text-text-secondary',
                        )}
                    >
                        {tab.label} {stocks.counts[tab.count]}
                    </Link>
                ))}
            </nav>
        </>
    );
}
