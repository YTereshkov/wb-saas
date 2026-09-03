import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import { ProductThumbnail } from '@/components/products/product-thumbnail';
import { cn } from '@/lib/utils';
import type { SalesQualityRow } from '@/types';

const number = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 });

function Product({ row }: { row: SalesQualityRow }) {
    return (
        <div className="flex min-w-0 items-center gap-3">
            <ProductThumbnail src={row.imageUrl} className="size-11" />
            <div className="min-w-0">
                <p className="truncate font-medium">{row.title}</p>
                <p className="truncate text-xs text-text-secondary">
                    {row.vendorCode}
                </p>
            </div>
        </div>
    );
}

export function QualityAttention({ rows }: { rows: SalesQualityRow[] }) {
    return (
        <section className="rounded-lg border bg-background p-5">
            <h2 className="text-base font-semibold">На контроле</h2>
            <div className="mt-3 divide-y">
                {rows.slice(0, 3).map((row) => (
                    <Link
                        key={row.id}
                        href={`/products/${row.id}/sales`}
                        className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 py-3"
                    >
                        <Product row={row} />
                        <div className="flex items-center gap-3">
                            <div className="text-right text-xs">
                                <p className="font-semibold text-destructive">
                                    {row.returnsRate === null
                                        ? '—'
                                        : `${number.format(row.returnsRate)}%`}
                                </p>
                                <p className="mt-1 text-text-secondary">
                                    возвраты
                                </p>
                            </div>
                            <ArrowRight className="size-4 text-muted-foreground" />
                        </div>
                    </Link>
                ))}
            </div>
            <Link
                href="/sales/products"
                className="mt-2 inline-flex text-sm font-semibold text-primary"
            >
                Открыть товары
            </Link>
        </section>
    );
}

export function SalesQualityTable({ rows }: { rows: SalesQualityRow[] }) {
    return (
        <section className="overflow-hidden rounded-lg border bg-background">
            <div className="px-5 py-4">
                <h2 className="text-base font-semibold">
                    Качество продаж по товарам
                </h2>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[980px] text-sm">
                    <thead className="border-y bg-surface-subtle text-xs text-text-secondary">
                        <tr>
                            <th className="min-w-72 px-5 py-3 text-left font-medium">
                                Товар
                            </th>
                            <th className="px-4 text-right font-medium">
                                Заказы
                            </th>
                            <th className="px-4 text-right font-medium">
                                Продажи
                            </th>
                            <th className="px-4 text-right font-medium">
                                Выкуп
                            </th>
                            <th className="px-4 text-right font-medium">
                                Возвраты
                            </th>
                            <th className="px-4 text-right font-medium">
                                Изменение выкупа
                            </th>
                            <th className="px-4 text-left font-medium">
                                Главный сигнал
                            </th>
                            <th className="w-10" />
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr
                                key={row.id}
                                className="h-[62px] border-b last:border-0"
                            >
                                <td className="px-5">
                                    <Product row={row} />
                                </td>
                                <td className="px-4 text-right tabular-nums">
                                    {row.orders}
                                </td>
                                <td className="px-4 text-right tabular-nums">
                                    {row.sales}
                                </td>
                                <td className="px-4 text-right tabular-nums">
                                    {row.buyout === null
                                        ? '—'
                                        : `${number.format(row.buyout)}%`}
                                </td>
                                <td className="px-4 text-right tabular-nums">
                                    {row.returnsRate === null
                                        ? '—'
                                        : `${number.format(row.returnsRate)}%`}
                                </td>
                                <td
                                    className={cn(
                                        'px-4 text-right font-medium tabular-nums',
                                        (row.buyoutChange ?? 0) < 0
                                            ? 'text-destructive'
                                            : 'text-success',
                                    )}
                                >
                                    {row.buyoutChange === null
                                        ? '—'
                                        : `${row.buyoutChange > 0 ? '+' : ''}${number.format(row.buyoutChange)} п.п.`}
                                </td>
                                <td
                                    className={cn(
                                        'px-4 text-xs',
                                        row.signal &&
                                            (row.signal.severity === 'danger'
                                                ? 'text-destructive'
                                                : 'text-warning'),
                                    )}
                                >
                                    {row.signal?.risk ?? '—'}
                                </td>
                                <td>
                                    <Link
                                        href={`/products/${row.id}/sales`}
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
