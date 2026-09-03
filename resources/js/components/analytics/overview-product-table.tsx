import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';

import { ProductThumbnail } from '@/components/products/product-thumbnail';
import { cn } from '@/lib/utils';
import type { OverviewData, ProductAnalyticsRow } from '@/types';

const money = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
});

type Products = NonNullable<OverviewData['products']>;
type Tab = keyof Products;

const tabs: Array<{ key: Tab; label: string }> = [
    { key: 'leaders', label: 'Лидеры продаж' },
    { key: 'decline', label: 'Снижение' },
    { key: 'risk', label: 'Риск по остаткам' },
];

function ProductCell({ row }: { row: ProductAnalyticsRow }) {
    return (
        <div className="flex min-w-0 items-center gap-3">
            <ProductThumbnail src={row.imageUrl} className="size-12" />
            <span className="min-w-0">
                <span className="block truncate text-sm font-semibold">
                    {row.title}
                </span>
                <span className="mt-1 block truncate text-xs text-muted-foreground">
                    {row.vendorCode}
                </span>
            </span>
        </div>
    );
}

export function OverviewProductTable({ products }: { products: Products }) {
    const [tab, setTab] = useState<Tab>('leaders');
    const rows = products[tab];

    return (
        <section className="overflow-hidden rounded-lg border border-border bg-background">
            <div className="flex flex-col justify-between gap-4 border-b border-border-subtle px-5 pt-5 sm:flex-row sm:items-end sm:px-6">
                <div>
                    <h2 className="text-base font-semibold">Товары</h2>
                    <div
                        className="mt-3 flex gap-5 overflow-x-auto"
                        role="tablist"
                        aria-label="Срез товаров"
                    >
                        {tabs.map((item) => (
                            <button
                                key={item.key}
                                type="button"
                                role="tab"
                                aria-selected={tab === item.key}
                                className={cn(
                                    'relative min-h-10 shrink-0 pb-3 text-sm font-medium text-text-secondary',
                                    tab === item.key &&
                                        'text-foreground after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:bg-primary',
                                )}
                                onClick={() => setTab(item.key)}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                </div>
                <Link
                    href="/products"
                    className="mb-3 hidden items-center gap-2 text-sm font-semibold text-primary sm:flex"
                >
                    Все товары <ArrowRight className="size-4" />
                </Link>
            </div>

            {rows.length === 0 ? (
                <div className="px-6 py-12 text-center">
                    <p className="text-sm font-semibold">
                        В этом срезе пока нет товаров
                    </p>
                    <p className="mt-1 text-xs text-text-secondary">
                        Данные появятся после следующей синхронизации.
                    </p>
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] border-collapse text-sm">
                        <thead className="bg-surface-subtle text-left text-xs font-medium text-text-secondary">
                            <tr>
                                <th className="px-6 py-3">Товар</th>
                                <th className="px-4 py-3 text-right">
                                    Выручка
                                </th>
                                <th className="px-4 py-3 text-right">
                                    Продажи
                                </th>
                                <th className="px-4 py-3 text-right">
                                    Динамика
                                </th>
                                <th className="px-4 py-3 text-right">
                                    Остаток
                                </th>
                                <th className="px-6 py-3 text-right">
                                    Хватит на
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border-subtle">
                            {rows.map((row) => (
                                <tr
                                    key={row.id}
                                    className="h-[68px] hover:bg-surface-subtle/70"
                                >
                                    <td className="max-w-[360px] px-6 py-2">
                                        <Link
                                            href={`/products/${row.id}`}
                                            className="block"
                                        >
                                            <ProductCell row={row} />
                                        </Link>
                                    </td>
                                    <td className="px-4 py-2 text-right font-medium tabular-nums">
                                        {money.format(row.revenueKopecks / 100)}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {row.sales.toLocaleString('ru-RU')}
                                    </td>
                                    <td
                                        className={cn(
                                            'px-4 py-2 text-right font-medium tabular-nums',
                                            (row.dynamics ?? 0) >= 0
                                                ? 'text-success'
                                                : 'text-destructive',
                                        )}
                                    >
                                        {row.dynamics === null
                                            ? '—'
                                            : `${row.dynamics > 0 ? '+' : ''}${row.dynamics}%`}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {row.stock} шт.
                                    </td>
                                    <td
                                        className={cn(
                                            'px-6 py-2 text-right font-medium tabular-nums',
                                            row.coverage !== null &&
                                                row.coverage <= 7 &&
                                                'text-warning',
                                        )}
                                    >
                                        {row.coverage === null
                                            ? '—'
                                            : `${Math.round(row.coverage)} дн.`}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}
