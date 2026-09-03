import { Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowRight,
    ArrowUp,
    ChevronLeft,
    ChevronRight,
    Info,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import { useState } from 'react';

import { EmptyState } from '@/components/feedback/data-states';
import { ProductThumbnail } from '@/components/products/product-thumbnail';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import type { StockAnalyticsData, StockRow, StockView } from '@/types';

const number = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 });
const routes: Record<StockView, string> = {
    all: '/stocks',
    supply: '/stocks/supply',
    out_of_stock: '/stocks/out-of-stock',
    no_movement: '/stocks/no-movement',
};

type ReadyStocks = StockAnalyticsData &
    Required<
        Pick<
            StockAnalyticsData,
            'rows' | 'pagination' | 'filters' | 'categories' | 'warehouses'
        >
    >;

function clean(values: Record<string, string | number | null | undefined>) {
    return Object.fromEntries(
        Object.entries(values).filter(
            ([, value]) =>
                value !== '' && value !== null && value !== undefined,
        ),
    );
}

function visit(
    stocks: ReadyStocks,
    updates: Record<string, string | number | null | undefined>,
) {
    router.get(
        routes[stocks.view],
        clean({
            ...stocks.filters,
            per_page: stocks.pagination.perPage,
            ...updates,
        }),
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function Sort({
    stocks,
    column,
    children,
    left = false,
}: {
    stocks: ReadyStocks;
    column: string;
    children: React.ReactNode;
    left?: boolean;
}) {
    const active = stocks.filters.sort === column;
    const Icon = stocks.filters.direction === 'asc' ? ArrowUp : ArrowDown;

    return (
        <button
            type="button"
            className={cn(
                'inline-flex w-full items-center gap-1 font-medium',
                left ? 'justify-start' : 'justify-end',
            )}
            onClick={() =>
                visit(stocks, {
                    sort: column,
                    direction:
                        active && stocks.filters.direction === 'desc'
                            ? 'asc'
                            : 'desc',
                    page: 1,
                })
            }
        >
            {children}
            {active && <Icon className="size-3.5 text-primary" />}
        </button>
    );
}

function Toolbar({ stocks }: { stocks: ReadyStocks }) {
    const [search, setSearch] = useState(stocks.filters.search);
    const [warehouse, setWarehouse] = useState(stocks.filters.warehouse ?? '');
    const [category, setCategory] = useState(stocks.filters.category ?? '');

    return (
        <div className="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-center">
            <form
                className="relative flex-1 lg:max-w-[340px]"
                onSubmit={(event) => {
                    event.preventDefault();
                    visit(stocks, { search, page: 1 });
                }}
            >
                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    name="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    aria-label="Поиск товаров"
                    placeholder="Название, артикул или nmID"
                    className="h-[42px] w-full rounded-md border bg-background pr-3 pl-10 text-sm outline-none focus:border-primary"
                />
            </form>
            <select
                name="warehouse"
                value={stocks.filters.warehouse ?? ''}
                aria-label="Склад"
                className="hidden h-[42px] min-w-40 rounded-md border bg-background px-3 text-sm md:block"
                onChange={(event) =>
                    visit(stocks, {
                        warehouse: event.target.value || null,
                        page: 1,
                    })
                }
            >
                <option value="">Все склады</option>
                {stocks.warehouses.map((item) => (
                    <option key={item.id} value={item.id}>
                        {item.name}
                    </option>
                ))}
            </select>
            <select
                name="category"
                value={stocks.filters.category ?? ''}
                aria-label="Категория"
                className="hidden h-[42px] min-w-44 rounded-md border bg-background px-3 text-sm md:block"
                onChange={(event) =>
                    visit(stocks, {
                        category: event.target.value || null,
                        page: 1,
                    })
                }
            >
                <option value="">Все категории</option>
                {stocks.categories.map((item) => (
                    <option key={item.id} value={item.id}>
                        {item.name}
                    </option>
                ))}
            </select>
            <Sheet>
                <SheetTrigger asChild>
                    <Button
                        variant="outline"
                        className="justify-start md:hidden"
                    >
                        <SlidersHorizontal />
                        Фильтры
                    </Button>
                </SheetTrigger>
                <SheetContent
                    side="bottom"
                    className="max-h-[85vh] rounded-t-lg"
                >
                    <SheetHeader>
                        <SheetTitle>Фильтры остатков</SheetTitle>
                        <SheetDescription>
                            Склад и категория товара
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4">
                        <label className="text-sm font-medium">
                            Склад
                            <select
                                value={warehouse}
                                onChange={(event) =>
                                    setWarehouse(event.target.value)
                                }
                                className="mt-2 h-[44px] w-full rounded-md border bg-background px-3 font-normal"
                            >
                                <option value="">Все склады</option>
                                {stocks.warehouses.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="text-sm font-medium">
                            Категория
                            <select
                                value={category}
                                onChange={(event) =>
                                    setCategory(event.target.value)
                                }
                                className="mt-2 h-[44px] w-full rounded-md border bg-background px-3 font-normal"
                            >
                                <option value="">Все категории</option>
                                {stocks.categories.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                    <SheetFooter>
                        <Button
                            onClick={() =>
                                visit(stocks, {
                                    warehouse: warehouse || null,
                                    category: category || null,
                                    page: 1,
                                })
                            }
                        >
                            Применить
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                visit(stocks, {
                                    warehouse: null,
                                    category: null,
                                    page: 1,
                                })
                            }
                        >
                            Сбросить
                        </Button>
                    </SheetFooter>
                </SheetContent>
            </Sheet>
        </div>
    );
}

function Product({ row }: { row: StockRow }) {
    return (
        <div className="flex min-w-0 items-center gap-3">
            <ProductThumbnail src={row.imageUrl} className="size-12" />
            <div className="min-w-0">
                <p className="truncate font-medium">{row.title}</p>
                <p className="truncate text-xs text-text-secondary">
                    {row.vendorCode} · {row.nmId}
                </p>
            </div>
        </div>
    );
}

function Signal({ row }: { row: StockRow }) {
    return (
        <span
            className={cn(
                'text-xs font-medium',
                row.signalTone === 'danger'
                    ? 'text-destructive'
                    : row.signalTone === 'warning'
                      ? 'text-warning'
                      : 'text-primary',
            )}
        >
            {row.signal?.risk ?? row.action}
        </span>
    );
}

function DesktopRows({ stocks }: { stocks: ReadyStocks }) {
    const isOut = stocks.view === 'out_of_stock';
    const isNoMovement = stocks.view === 'no_movement';

    return (
        <div className="hidden overflow-x-auto md:block">
            <table className="w-full min-w-[1080px] text-sm">
                <thead className="border-y bg-surface-subtle text-xs text-text-secondary">
                    <tr>
                        <th className="min-w-72 px-5 py-3 text-left">
                            <Sort stocks={stocks} column="title" left>
                                Товар
                            </Sort>
                        </th>
                        <th className="px-4 text-right">
                            <Sort stocks={stocks} column="stock">
                                Остаток, шт.
                            </Sort>
                        </th>
                        <th className="px-4 text-right">
                            <Sort stocks={stocks} column="sales">
                                Продажи в день
                            </Sort>
                        </th>
                        {isOut ? (
                            <>
                                <th className="px-4 text-right">
                                    <Sort stocks={stocks} column="out_days">
                                        Без остатка, дней
                                    </Sort>
                                </th>
                                <th className="px-4 text-right">
                                    Оценка упущенных продаж
                                </th>
                            </>
                        ) : isNoMovement ? (
                            <>
                                <th className="px-4 text-right">
                                    Продажи за 30 дней
                                </th>
                                <th className="px-4 text-right">
                                    Дней без продаж
                                </th>
                                <th className="px-4 text-right">
                                    <Sort stocks={stocks} column="coverage">
                                        Запас, дней
                                    </Sort>
                                </th>
                            </>
                        ) : (
                            <>
                                <th className="px-4 text-right">
                                    <Sort stocks={stocks} column="coverage">
                                        Запас, дней
                                    </Sort>
                                </th>
                                <th className="px-4 text-right">Закончится</th>
                            </>
                        )}
                        <th className="px-4 text-right">
                            <Sort stocks={stocks} column="supply">
                                Поставка на 30 дней
                            </Sort>
                        </th>
                        <th className="px-4 text-left">
                            {isNoMovement ? 'Причина' : 'Главный сигнал'}
                        </th>
                        <th className="w-10" />
                    </tr>
                </thead>
                <tbody>
                    {stocks.rows.map((row) => (
                        <tr
                            key={row.id}
                            className="h-[68px] border-b last:border-0"
                        >
                            <td className="px-5">
                                <Product row={row} />
                            </td>
                            <td
                                className={cn(
                                    'px-4 text-right font-medium tabular-nums',
                                    row.stock === 0
                                        ? 'text-destructive'
                                        : (row.coverage ?? 999) <= 7
                                          ? 'text-warning'
                                          : '',
                                )}
                            >
                                {row.stock}
                            </td>
                            <td className="px-4 text-right tabular-nums">
                                {number.format(row.averageDailySales)}
                            </td>
                            {isOut ? (
                                <>
                                    <td className="px-4 text-right text-destructive">
                                        {row.outOfStockDays}
                                    </td>
                                    <td className="px-4 text-right">
                                        {number.format(
                                            row.estimatedMissedSales,
                                        )}{' '}
                                        шт.
                                    </td>
                                </>
                            ) : isNoMovement ? (
                                <>
                                    <td className="px-4 text-right">
                                        {row.recentSales}
                                    </td>
                                    <td className="px-4 text-right">
                                        {row.daysWithoutSales}
                                    </td>
                                    <td className="px-4 text-right">
                                        {row.coverage === null
                                            ? '—'
                                            : row.coverage > 90
                                              ? '>90'
                                              : number.format(row.coverage)}
                                    </td>
                                </>
                            ) : (
                                <>
                                    <td
                                        className={cn(
                                            'px-4 text-right',
                                            (row.coverage ?? 999) <= 7 &&
                                                'text-warning',
                                        )}
                                    >
                                        {row.coverage === null
                                            ? '—'
                                            : number.format(row.coverage)}
                                    </td>
                                    <td className="px-4 text-right">
                                        {row.stock === 0
                                            ? 'Уже закончился'
                                            : (row.outDate ?? '—')}
                                    </td>
                                </>
                            )}
                            <td className="px-4 text-right">
                                {row.recommendedSupply} шт.
                            </td>
                            <td className="px-4">
                                {isNoMovement ? (
                                    <span className="text-xs text-primary">
                                        {row.noMovementReason}
                                    </span>
                                ) : (
                                    <Signal row={row} />
                                )}
                            </td>
                            <td>
                                <Link
                                    href={`/products/${row.id}/stocks`}
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
    );
}

export function StockTable({ stocks }: { stocks: ReadyStocks }) {
    return (
        <section className="overflow-hidden rounded-lg border bg-background">
            <Toolbar key={JSON.stringify(stocks.filters)} stocks={stocks} />
            {stocks.rows.length === 0 ? (
                <div className="p-4">
                    <EmptyState
                        title="Остатков пока нет"
                        description="Данные появятся после синхронизации."
                        filtered
                    />
                </div>
            ) : (
                <>
                    <div className="flex items-center gap-2 px-5 py-3 text-xs text-text-secondary">
                        <Info className="size-4 shrink-0" />
                        {stocks.notice}
                    </div>
                    <DesktopRows stocks={stocks} />
                    <div className="divide-y md:hidden">
                        {stocks.rows.map((row) => (
                            <Link
                                key={row.id}
                                href={`/products/${row.id}/stocks`}
                                className="block p-4"
                            >
                                <div className="flex items-center gap-3">
                                    <Product row={row} />
                                    <ArrowRight className="ml-auto size-4 shrink-0" />
                                </div>
                                <div className="mt-4 grid grid-cols-3 gap-2 text-xs">
                                    <span>
                                        <b className="block text-base">
                                            {row.stock}
                                        </b>
                                        остаток
                                    </span>
                                    <span>
                                        <b className="block text-base">
                                            {number.format(
                                                row.averageDailySales,
                                            )}
                                        </b>
                                        в день
                                    </span>
                                    <span>
                                        <b className="block text-base">
                                            {row.coverage === null
                                                ? '—'
                                                : row.coverage > 90
                                                  ? '>90'
                                                  : number.format(row.coverage)}
                                        </b>
                                        дней
                                    </span>
                                </div>
                                <p
                                    className={cn(
                                        'mt-3 text-xs font-medium',
                                        row.signalTone === 'danger'
                                            ? 'text-destructive'
                                            : row.signalTone === 'warning'
                                              ? 'text-warning'
                                              : 'text-primary',
                                    )}
                                >
                                    {stocks.view === 'no_movement'
                                        ? row.noMovementReason
                                        : row.action}
                                </p>
                            </Link>
                        ))}
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4 text-sm">
                        <span>
                            Показано {stocks.pagination.from ?? 0}–
                            {stocks.pagination.to ?? 0} из{' '}
                            {stocks.pagination.total}
                        </span>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                aria-label="Предыдущая страница"
                                disabled={stocks.pagination.currentPage <= 1}
                                className="grid size-9 place-items-center rounded-md border disabled:opacity-40"
                                onClick={() =>
                                    visit(stocks, {
                                        page: stocks.pagination.currentPage - 1,
                                    })
                                }
                            >
                                <ChevronLeft className="size-4" />
                            </button>
                            <span className="grid size-9 place-items-center rounded-md border border-primary text-primary">
                                {stocks.pagination.currentPage}
                            </span>
                            <button
                                type="button"
                                aria-label="Следующая страница"
                                disabled={
                                    stocks.pagination.currentPage >=
                                    stocks.pagination.lastPage
                                }
                                className="grid size-9 place-items-center rounded-md border disabled:opacity-40"
                                onClick={() =>
                                    visit(stocks, {
                                        page: stocks.pagination.currentPage + 1,
                                    })
                                }
                            >
                                <ChevronRight className="size-4" />
                            </button>
                        </div>
                    </div>
                </>
            )}
        </section>
    );
}
