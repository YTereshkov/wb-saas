import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import { ProductThumbnail } from '@/components/products/product-thumbnail';
import { cn } from '@/lib/utils';
import type { SalesAnalyticsData } from '@/types';

const money = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
});

export function ContributionTable({
    rows,
}: {
    rows: NonNullable<SalesAnalyticsData['products']>['contribution'];
}) {
    const max = Math.max(1, ...rows.map((row) => Math.abs(row.deltaKopecks)));

    return (
        <section className="overflow-hidden rounded-lg border bg-background">
            <div className="flex items-center justify-between px-5 py-4">
                <h2 className="text-base font-semibold">
                    Вклад товаров в изменение выручки
                </h2>
                <Link
                    href="/products"
                    className="text-xs font-medium text-primary"
                >
                    Открыть все товары
                </Link>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[780px] text-sm">
                    <thead className="border-y bg-surface-subtle text-xs text-text-secondary">
                        <tr>
                            <th className="px-5 py-2.5 text-left font-medium">
                                Товар
                            </th>
                            <th className="px-4 py-2.5 text-right font-medium">
                                Выручка
                            </th>
                            <th className="px-4 py-2.5 text-right font-medium">
                                Прошлый период
                            </th>
                            <th className="px-4 py-2.5 text-right font-medium">
                                Изменение
                            </th>
                            <th className="w-52 px-4 py-2.5 text-left font-medium">
                                Вклад в рост
                            </th>
                            <th className="w-10" />
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr
                                key={row.id}
                                className="h-[58px] border-b last:border-0"
                            >
                                <td className="px-5">
                                    <div className="flex items-center gap-3">
                                        <ProductThumbnail
                                            src={row.imageUrl}
                                            className="size-10"
                                        />
                                        <div>
                                            <p className="font-medium">
                                                {row.title}
                                            </p>
                                            <p className="text-xs text-text-secondary">
                                                {row.vendorCode}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-4 text-right tabular-nums">
                                    {money.format(row.revenueKopecks / 100)}
                                </td>
                                <td className="px-4 text-right tabular-nums">
                                    {money.format(
                                        row.comparisonRevenueKopecks / 100,
                                    )}
                                </td>
                                <td
                                    className={cn(
                                        'px-4 text-right font-medium',
                                        row.deltaKopecks >= 0
                                            ? 'text-success'
                                            : 'text-destructive',
                                    )}
                                >
                                    {row.dynamics === null
                                        ? '—'
                                        : `${row.dynamics > 0 ? '+' : ''}${row.dynamics}%`}
                                </td>
                                <td className="px-4">
                                    <div className="flex items-center gap-3">
                                        <span
                                            className={cn(
                                                'h-1.5 rounded',
                                                row.deltaKopecks >= 0
                                                    ? 'bg-primary'
                                                    : 'bg-destructive',
                                            )}
                                            style={{
                                                width: `${Math.max(12, (Math.abs(row.deltaKopecks) / max) * 112)}px`,
                                            }}
                                        />
                                        <span
                                            className={cn(
                                                'text-xs tabular-nums',
                                                row.deltaKopecks >= 0
                                                    ? 'text-success'
                                                    : 'text-destructive',
                                            )}
                                        >
                                            {row.deltaKopecks >= 0 ? '+' : ''}
                                            {money.format(
                                                row.deltaKopecks / 100,
                                            )}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <Link
                                        href={`/products/${row.id}`}
                                        aria-label={`Открыть ${row.title}`}
                                    >
                                        <ArrowRight className="size-4 text-muted-foreground" />
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export function GapTable({
    rows,
}: {
    rows: NonNullable<SalesAnalyticsData['products']>['gap'];
}) {
    return (
        <section className="overflow-hidden rounded-lg border bg-background">
            <div className="flex items-center justify-between px-5 py-4">
                <h2 className="text-base font-semibold">
                    Товары с наибольшей разницей
                </h2>
                <Link
                    href="/products"
                    className="text-xs font-medium text-primary"
                >
                    Открыть все товары
                </Link>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[760px] text-sm">
                    <thead className="border-y bg-surface-subtle text-xs text-text-secondary">
                        <tr>
                            <th className="px-5 py-2.5 text-left font-medium">
                                Товар
                            </th>
                            <th className="px-4 text-right font-medium">
                                Заказы
                            </th>
                            <th className="px-4 text-right font-medium">
                                Продажи
                            </th>
                            <th className="px-4 text-right font-medium">
                                Разница
                            </th>
                            <th className="px-4 text-right font-medium">
                                Выкуп
                            </th>
                            <th className="w-12" />
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr
                                key={row.id}
                                className="h-[58px] border-b last:border-0"
                            >
                                <td className="px-5">
                                    <div className="flex items-center gap-3">
                                        <ProductThumbnail
                                            src={row.imageUrl}
                                            className="size-10"
                                        />
                                        <div>
                                            <p className="font-medium">
                                                {row.title}
                                            </p>
                                            <p className="text-xs text-text-secondary">
                                                {row.vendorCode}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-4 text-right">
                                    {row.orders}
                                </td>
                                <td className="px-4 text-right">{row.sales}</td>
                                <td className="px-4 text-right font-medium">
                                    {row.gap}
                                </td>
                                <td className="px-4 text-right">
                                    {row.buyout === null
                                        ? '—'
                                        : `${row.buyout}%`}
                                </td>
                                <td>
                                    <Link
                                        href={`/products/${row.id}`}
                                        aria-label={`Открыть ${row.title}`}
                                    >
                                        <ArrowRight className="size-4 text-muted-foreground" />
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
