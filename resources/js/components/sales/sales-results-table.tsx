import { Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowRight,
    ArrowUp,
    ChevronLeft,
    ChevronRight,
    SlidersHorizontal,
    Search,
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
import type { SalesProductTableData } from '@/types';

const money = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
});

type ReadyTable = SalesProductTableData &
    Required<
        Pick<
            SalesProductTableData,
            'rows' | 'pagination' | 'filters' | 'categories'
        >
    >;

function clean(values: Record<string, string | number | null | undefined>) {
    const selectKeys = new Set(['category', 'performance']);

    return Object.fromEntries(
        Object.entries(values).filter(
            ([key, value]) =>
                value !== '' &&
                value !== null &&
                value !== undefined &&
                !(value === 'all' && selectKeys.has(key)),
        ),
    );
}

function visit(
    table: ReadyTable,
    updates: Record<string, string | number | null | undefined>,
) {
    router.get(
        '/sales/products',
        clean({
            ...table.filters,
            per_page: table.pagination.perPage,
            ...updates,
        }),
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function Sort({
    table,
    column,
    children,
    left = false,
}: {
    table: ReadyTable;
    column: string;
    children: React.ReactNode;
    left?: boolean;
}) {
    const active = table.filters.sort === column;
    const Icon = table.filters.direction === 'asc' ? ArrowUp : ArrowDown;

    return (
        <button
            type="button"
            className={cn(
                'inline-flex w-full items-center gap-1 font-medium',
                left ? 'justify-start' : 'justify-end',
            )}
            onClick={() =>
                visit(table, {
                    sort: column,
                    direction:
                        active && table.filters.direction === 'desc'
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

function Toolbar({ table }: { table: ReadyTable }) {
    const [search, setSearch] = useState(table.filters.search);
    const [category, setCategory] = useState(table.filters.category ?? '');
    const [performance, setPerformance] = useState(table.filters.performance);

    return (
        <div className="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-center">
            <form
                className="relative flex-1 lg:max-w-[360px]"
                onSubmit={(event) => {
                    event.preventDefault();
                    visit(table, { search, page: 1 });
                }}
            >
                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    name="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Название, артикул или nmID"
                    aria-label="Поиск товаров"
                    className="h-[42px] w-full rounded-md border bg-background pr-3 pl-10 text-sm outline-none focus:border-primary"
                />
            </form>
            <select
                name="category"
                value={table.filters.category ?? ''}
                aria-label="Категория"
                className="hidden h-[42px] min-w-44 rounded-md border bg-background px-3 text-sm md:block"
                onChange={(event) =>
                    visit(table, {
                        category: event.target.value || null,
                        page: 1,
                    })
                }
            >
                <option value="">Все категории</option>
                {table.categories.map((item) => (
                    <option key={item.id} value={item.id}>
                        {item.name}
                    </option>
                ))}
            </select>
            <select
                name="performance"
                value={table.filters.performance}
                aria-label="Динамика"
                className="hidden h-[42px] min-w-40 rounded-md border bg-background px-3 text-sm md:block"
                onChange={(event) =>
                    visit(table, { performance: event.target.value, page: 1 })
                }
            >
                <option value="all">Любая динамика</option>
                <option value="growth">Рост</option>
                <option value="decline">Снижение</option>
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
                        <SheetTitle>Фильтры товаров</SheetTitle>
                        <SheetDescription>
                            Категория и динамика продаж
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-4 px-4">
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
                                {table.categories.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="text-sm font-medium">
                            Динамика
                            <select
                                value={performance}
                                onChange={(event) =>
                                    setPerformance(event.target.value)
                                }
                                className="mt-2 h-[44px] w-full rounded-md border bg-background px-3 font-normal"
                            >
                                <option value="all">Любая динамика</option>
                                <option value="growth">Рост</option>
                                <option value="decline">Снижение</option>
                            </select>
                        </label>
                    </div>
                    <SheetFooter>
                        <Button
                            onClick={() =>
                                visit(table, {
                                    category: category || null,
                                    performance,
                                    page: 1,
                                })
                            }
                        >
                            Применить
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                visit(table, {
                                    category: null,
                                    performance: 'all',
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

export function SalesResultsTable({ table }: { table: ReadyTable }) {
    return (
        <section className="overflow-hidden rounded-lg border bg-background">
            <Toolbar key={JSON.stringify(table.filters)} table={table} />
            {table.rows.length === 0 ? (
                <div className="p-4">
                    <EmptyState
                        title="Товаров пока нет"
                        description="Результаты появятся после синхронизации."
                        filtered
                    />
                </div>
            ) : (
                <>
                    <div className="px-5 py-4">
                        <h2 className="text-base font-semibold">
                            Результаты по товарам
                        </h2>
                        <p className="mt-1 text-xs text-text-secondary">
                            {table.pagination.total} товаров
                        </p>
                    </div>
                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full min-w-[1080px] text-sm">
                            <thead className="border-y bg-surface-subtle text-xs text-text-secondary">
                                <tr>
                                    <th className="min-w-72 px-5 py-3 text-left">
                                        <Sort table={table} column="title" left>
                                            Товар
                                        </Sort>
                                    </th>
                                    <th className="px-4 text-right">
                                        <Sort table={table} column="revenue">
                                            Выручка
                                        </Sort>
                                    </th>
                                    <th className="px-4 text-right">
                                        <Sort table={table} column="comparison">
                                            Прошлый период
                                        </Sort>
                                    </th>
                                    <th className="px-4 text-right">
                                        <Sort table={table} column="dynamics">
                                            Изменение
                                        </Sort>
                                    </th>
                                    <th className="px-4 text-right">
                                        <Sort table={table} column="sales">
                                            Продажи
                                        </Sort>
                                    </th>
                                    <th className="px-4 text-right">
                                        <Sort table={table} column="buyout">
                                            Выкуп
                                        </Sort>
                                    </th>
                                    <th className="px-4 text-left">
                                        Главный сигнал
                                    </th>
                                    <th className="w-10" />
                                </tr>
                            </thead>
                            <tbody>
                                {table.rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="h-[64px] border-b last:border-0"
                                    >
                                        <td className="px-5">
                                            <div className="flex items-center gap-3">
                                                <ProductThumbnail
                                                    src={row.imageUrl}
                                                    className="size-11"
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
                                            {money.format(
                                                row.revenueKopecks / 100,
                                            )}
                                        </td>
                                        <td className="px-4 text-right tabular-nums">
                                            {money.format(
                                                row.comparisonRevenueKopecks /
                                                    100,
                                            )}
                                        </td>
                                        <td
                                            className={cn(
                                                'px-4 text-right font-medium',
                                                row.dynamics !== null &&
                                                    row.dynamics < 0 &&
                                                    'text-destructive',
                                                row.dynamics !== null &&
                                                    row.dynamics > 0 &&
                                                    'text-success',
                                            )}
                                        >
                                            {row.dynamics === null
                                                ? '—'
                                                : `${row.dynamics > 0 ? '+' : ''}${row.dynamics}%`}
                                        </td>
                                        <td className="px-4 text-right">
                                            {row.sales}
                                        </td>
                                        <td className="px-4 text-right">
                                            {row.buyout === null
                                                ? '—'
                                                : `${row.buyout}%`}
                                        </td>
                                        <td
                                            className={cn(
                                                'px-4 text-xs',
                                                row.signal?.severity ===
                                                    'danger'
                                                    ? 'text-destructive'
                                                    : 'text-warning',
                                            )}
                                        >
                                            {row.signal?.risk ?? '—'}
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
                    <div className="divide-y md:hidden">
                        {table.rows.map((row) => (
                            <Link
                                key={row.id}
                                href={`/products/${row.id}`}
                                className="block p-4"
                            >
                                <div className="flex items-center gap-3">
                                    <ProductThumbnail
                                        src={row.imageUrl}
                                        className="size-12"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-semibold">
                                            {row.title}
                                        </p>
                                        <p className="text-xs text-text-secondary">
                                            {row.vendorCode}
                                        </p>
                                    </div>
                                    <ArrowRight className="size-4" />
                                </div>
                                <div className="mt-4 grid grid-cols-3 gap-2 text-xs">
                                    <span>
                                        <b className="block text-sm">
                                            {money.format(
                                                row.revenueKopecks / 100,
                                            )}
                                        </b>
                                        выручка
                                    </span>
                                    <span>
                                        <b className="block text-sm">
                                            {row.sales}
                                        </b>
                                        продажи
                                    </span>
                                    <span>
                                        <b className="block text-sm">
                                            {row.buyout === null
                                                ? '—'
                                                : `${row.buyout}%`}
                                        </b>
                                        выкуп
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4 text-sm">
                        <span>
                            Показано {table.pagination.from ?? 0}–
                            {table.pagination.to ?? 0} из{' '}
                            {table.pagination.total}
                        </span>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                aria-label="Предыдущая страница"
                                disabled={table.pagination.currentPage <= 1}
                                className="grid size-9 place-items-center rounded-md border disabled:opacity-40"
                                onClick={() =>
                                    visit(table, {
                                        page: table.pagination.currentPage - 1,
                                    })
                                }
                            >
                                <ChevronLeft className="size-4" />
                            </button>
                            <span className="grid size-9 place-items-center rounded-md border border-primary text-primary">
                                {table.pagination.currentPage}
                            </span>
                            <button
                                type="button"
                                aria-label="Следующая страница"
                                disabled={
                                    table.pagination.currentPage >=
                                    table.pagination.lastPage
                                }
                                className="grid size-9 place-items-center rounded-md border disabled:opacity-40"
                                onClick={() =>
                                    visit(table, {
                                        page: table.pagination.currentPage + 1,
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
