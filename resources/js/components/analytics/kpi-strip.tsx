import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';

import { cn } from '@/lib/utils';
import type { KpiItem } from '@/types';

const money = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
});
const number = new Intl.NumberFormat('ru-RU');

function valueLabel(kpi: KpiItem) {
    if (kpi.value === null) {
        return '—';
    }

    if (kpi.format === 'money') {
        return money.format(kpi.value / 100);
    }

    if (kpi.format === 'percent') {
        return `${number.format(kpi.value)}%`;
    }

    return number.format(kpi.value);
}

function Change({ kpi }: { kpi: KpiItem }) {
    const positive = (kpi.change ?? 0) > 0;
    const negative = (kpi.change ?? 0) < 0;
    const favourable = kpi.inverse ? negative : positive;
    const harmful = kpi.inverse ? positive : negative;
    const Icon = positive ? ArrowUpRight : negative ? ArrowDownRight : Minus;
    const suffix = kpi.changeFormat === 'pp' ? ' п.п.' : '%';

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 text-xs font-semibold',
                favourable && 'text-success',
                harmful && 'text-destructive',
                !positive && !negative && 'text-muted-foreground',
            )}
        >
            <Icon className="size-3.5" aria-hidden="true" />
            {kpi.change === null
                ? 'Нет данных'
                : `${Math.abs(kpi.change).toLocaleString('ru-RU')}${suffix}`}
        </span>
    );
}

export function KpiStrip({ items }: { items: KpiItem[] }) {
    return (
        <section
            className={cn(
                'grid grid-cols-2 overflow-hidden rounded-lg border border-border bg-background',
                items.length === 5 ? 'xl:grid-cols-5' : 'xl:grid-cols-4',
            )}
        >
            {items.map((item, index) => (
                <article
                    key={item.key}
                    className={cn(
                        'min-h-[116px] px-4 py-4 sm:px-6',
                        index % 2 === 1 && 'border-l',
                        index >= 2 && 'border-t',
                        index > 0 && 'xl:border-t-0 xl:border-l',
                        items.length % 2 === 1 &&
                            index === items.length - 1 &&
                            'col-span-2 xl:col-span-1',
                    )}
                >
                    <p className="text-sm text-text-secondary">{item.label}</p>
                    <p className="mt-2 text-[25px] leading-8 font-semibold tabular-nums">
                        {valueLabel(item)}
                    </p>
                    <div className="mt-1.5 flex items-center gap-2">
                        <Change kpi={item} />
                        <span className="hidden text-xs text-muted-foreground sm:inline">
                            к прошлому периоду
                        </span>
                    </div>
                </article>
            ))}
        </section>
    );
}
