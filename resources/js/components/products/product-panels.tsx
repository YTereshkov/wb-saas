import { Link } from '@inertiajs/react';
import { ArrowRight, CircleAlert } from 'lucide-react';

import { formatDays } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ProductDetailsData } from '@/types';

const number = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 });

export function PrimarySignalPanel({
    details,
}: {
    details: ProductDetailsData;
}) {
    const signal = details.signal;

    return (
        <section className="rounded-lg border border-border bg-background p-5 sm:p-6">
            <h2 className="text-base font-semibold">Главный сигнал</h2>
            {signal ? (
                <div className="mt-4">
                    <div className="flex items-center gap-2 text-warning">
                        <CircleAlert className="size-4" />
                        <span className="text-sm font-medium">
                            {signal.fact}
                        </span>
                    </div>
                    {details.stock.coverage !== null && (
                        <p className="mt-3 text-[30px] leading-9 font-semibold text-warning">
                            {formatDays(Math.floor(details.stock.coverage))}
                        </p>
                    )}
                    <p className="mt-1 text-xs text-text-secondary">
                        {details.stock.total} шт. в наличии
                    </p>
                    <p className="mt-4 text-sm leading-5 text-text-secondary">
                        {signal.risk}
                    </p>
                    <p className="mt-2 text-sm leading-5">
                        {signal.recommendation}
                    </p>
                    <Link
                        href={`/products/${details.product.id}/stocks`}
                        className="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-primary"
                    >
                        Перейти к остаткам <ArrowRight className="size-4" />
                    </Link>
                </div>
            ) : (
                <p className="mt-4 text-sm text-text-secondary">
                    Критичных сигналов по товару нет.
                </p>
            )}
        </section>
    );
}

export function SalesFunnel({
    funnel,
    compact = false,
}: {
    funnel: ProductDetailsData['funnel'];
    compact?: boolean;
}) {
    const fullItems = [
        { label: 'заказов', value: number.format(funnel.orders) },
        { label: 'продажи', value: number.format(funnel.sales) },
        { label: 'не выкуплено', value: number.format(funnel.notBought) },
        {
            label: 'выкуп',
            value:
                funnel.buyout === null
                    ? '—'
                    : `${number.format(funnel.buyout)}%`,
        },
        {
            label: 'возвраты',
            value:
                funnel.returnsRate === null
                    ? '—'
                    : `${number.format(funnel.returnsRate)}%`,
        },
    ];
    const items = compact
        ? [fullItems[0], fullItems[1], fullItems[3]]
        : fullItems;

    return (
        <section className="rounded-lg border border-border bg-background p-5">
            <h2 className="text-base font-semibold">Заказы и выкуп</h2>
            <div
                className={cn(
                    'mt-7 grid gap-5',
                    compact ? 'grid-cols-3' : 'grid-cols-2 sm:grid-cols-5',
                )}
            >
                {items.map((item, index) => (
                    <div key={item.label} className="relative text-center">
                        <p className="text-[22px] font-semibold tabular-nums">
                            {item.value}
                        </p>
                        <p className="mt-1 text-xs text-text-secondary">
                            {item.label}
                        </p>
                        {index < items.length - 1 && (
                            <ArrowRight className="absolute top-2 -right-3 hidden size-4 text-muted-foreground sm:block" />
                        )}
                    </div>
                ))}
            </div>
        </section>
    );
}

export function WarehouseTable({
    warehouses,
    compact = false,
    detailsHref,
}: {
    warehouses: ProductDetailsData['stock']['warehouses'];
    compact?: boolean;
    detailsHref?: string;
}) {
    return (
        <section className="overflow-hidden rounded-lg border border-border bg-background">
            <div className="flex items-center justify-between px-5 py-4">
                <h2 className="text-base font-semibold">Остатки по складам</h2>
                {detailsHref && (
                    <Link
                        href={detailsHref}
                        className="text-xs font-medium text-primary"
                    >
                        Подробнее
                    </Link>
                )}
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[560px] text-sm">
                    <thead className="border-y bg-surface-subtle text-xs text-text-secondary">
                        <tr>
                            <th className="px-5 py-2.5 text-left font-medium">
                                Склад
                            </th>
                            <th className="px-5 py-2.5 text-right font-medium">
                                Остаток
                            </th>
                            {!compact && (
                                <th className="px-5 py-2.5 text-right font-medium">
                                    Доля
                                </th>
                            )}
                            <th className="px-5 py-2.5 text-right font-medium">
                                Запас, дней
                            </th>
                            {!compact && (
                                <th className="px-5 py-2.5 text-right font-medium">
                                    Срочность
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {warehouses.map((warehouse) => (
                            <tr
                                key={warehouse.name}
                                className="border-b last:border-0"
                            >
                                <td className="px-5 py-3 font-medium">
                                    {warehouse.name}
                                </td>
                                <td className="px-5 py-3 text-right tabular-nums">
                                    {warehouse.quantity}
                                </td>
                                {!compact && (
                                    <td className="px-5 py-3 text-right">
                                        {warehouse.share}%
                                    </td>
                                )}
                                <td
                                    className={cn(
                                        'px-5 py-3 text-right tabular-nums',
                                        (warehouse.coverage ?? 99) <= 3 &&
                                            'text-warning',
                                    )}
                                >
                                    {warehouse.coverage ?? '—'}
                                </td>
                                {!compact && (
                                    <td
                                        className={cn(
                                            'px-5 py-3 text-right',
                                            warehouse.urgency !==
                                                'Нормальная' &&
                                                'text-destructive',
                                        )}
                                    >
                                        {warehouse.urgency}
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export function StockMetricStrip({
    stock,
}: {
    stock: ProductDetailsData['stock'];
}) {
    const items = [
        { label: 'Остаток', value: `${stock.total} шт.`, danger: false },
        {
            label: 'Средние продажи',
            value: `${number.format(stock.averageDailySales)} шт./день`,
            danger: false,
        },
        {
            label: 'Запас',
            value:
                stock.coverage === null
                    ? '—'
                    : formatDays(Math.floor(stock.coverage)),
            danger: (stock.coverage ?? 99) <= 7,
        },
        {
            label: 'Закончится',
            value: stock.outDate ?? '—',
            danger: (stock.coverage ?? 99) <= 7,
        },
    ];

    return (
        <section className="grid overflow-hidden rounded-lg border bg-background sm:grid-cols-2 xl:grid-cols-4">
            {items.map((item, index) => (
                <article
                    key={item.label}
                    className={cn(
                        'min-h-24 px-5 py-4 sm:px-6',
                        index > 0 && 'border-t sm:border-t-0 sm:border-l',
                        index === 2 && 'sm:border-t xl:border-t-0',
                    )}
                >
                    <p className="text-xs text-text-secondary">{item.label}</p>
                    <p
                        className={cn(
                            'mt-2 text-[24px] font-semibold tabular-nums',
                            item.danger && 'text-warning',
                        )}
                    >
                        {item.value}
                    </p>
                </article>
            ))}
        </section>
    );
}
