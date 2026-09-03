import { Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ChevronLeft,
    ChevronRight,
    Columns3,
    Filter,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import { useState } from 'react';

import { ProductThumbnail } from '@/components/products/product-thumbnail';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { formatDays } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    ProductAnalyticsRow,
    ProductColumn,
    ProductTableData,
    ProductView,
} from '@/types';

const money = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
});

const routes: Record<ProductView, string> = {
    all: '/products',
    attention: '/products/attention',
    decline: '/products/decline',
    low_stock: '/products/low-stock',
};

const columnOptions: Array<{ key: ProductColumn; label: string }> = [
    { key: 'revenue', label: 'Выручка' },
    { key: 'sales', label: 'Продажи' },
    { key: 'dynamics', label: 'Динамика' },
    { key: 'buyout', label: 'Выкуп' },
    { key: 'returns', label: 'Возвраты' },
    { key: 'stock', label: 'Остаток' },
    { key: 'coverage', label: 'Хватит на' },
];

type ReadyTable = ProductTableData &
    Required<
        Pick<
            ProductTableData,
            | 'counts'
            | 'rows'
            | 'pagination'
            | 'filters'
            | 'columns'
            | 'categories'
        >
    >;

function clean(values: Record<string, string | number | null | undefined>) {
    const selectKeys = new Set(['category', 'status', 'stock', 'performance']);

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

function queryValues(table: ReadyTable) {
    return {
        search: table.filters.search,
        category: table.filters.category,
        status: table.filters.status,
        stock: table.filters.stock,
        performance: table.filters.performance,
        sort: table.filters.sort,
        direction: table.filters.direction,
        per_page: table.pagination.perPage,
    };
}

function visit(
    table: ReadyTable,
    updates: Record<string, string | number | null | undefined>,
) {
    router.get(
        routes[table.view],
        clean({ ...queryValues(table), ...updates }),
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function ProductIdentity({ row }: { row: ProductAnalyticsRow }) {
    return (
        <div className="flex min-w-0 items-center gap-3">
            <ProductThumbnail src={row.imageUrl} className="size-[50px]" />
            <span className="min-w-0">
                <span className="block truncate text-sm font-semibold">
                    {row.title}
                </span>
                <span className="mt-1 block truncate text-xs text-muted-foreground">
                    {row.vendorCode} · {row.nmId}
                </span>
                {row.signal && (
                    <span
                        className={cn(
                            'mt-1 block truncate text-xs font-medium',
                            row.signal.severity === 'danger'
                                ? 'text-destructive'
                                : 'text-warning',
                        )}
                    >
                        {row.signal.risk}
                    </span>
                )}
            </span>
        </div>
    );
}

function SortButton({
    table,
    column,
    children,
    align = 'right',
}: {
    table: ReadyTable;
    column: string;
    children: React.ReactNode;
    align?: 'left' | 'right';
}) {
    const active = table.filters.sort === column;
    const Icon = table.filters.direction === 'asc' ? ArrowUp : ArrowDown;

    return (
        <button
            type="button"
            className={cn(
                'inline-flex w-full items-center gap-1 font-medium hover:text-foreground',
                align === 'right' ? 'justify-end' : 'justify-start',
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
    const [stock, setStock] = useState(table.filters.stock);
    const [performance, setPerformance] = useState(table.filters.performance);

    return (
        <div className="flex flex-col gap-3 border-b border-border-subtle p-4 lg:flex-row lg:items-center">
            <form
                className="relative min-w-0 flex-1 lg:max-w-[360px]"
                onSubmit={(event) => {
                    event.preventDefault();
                    visit(table, { search, page: 1 });
                }}
            >
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Название, артикул или nmID"
                    aria-label="Поиск товаров"
                    className="h-[42px] w-full rounded-md border border-input bg-background pr-3 pl-10 text-sm outline-none placeholder:text-muted-foreground focus:border-primary focus:ring-3 focus:ring-ring/10"
                />
            </form>

            <select
                value={table.filters.category ?? ''}
                aria-label="Категория"
                className="h-[42px] min-w-40 rounded-md border border-input bg-background px-3 text-sm font-medium outline-none focus:border-primary"
                onChange={(event) =>
                    visit(table, {
                        category: event.target.value || null,
                        page: 1,
                    })
                }
            >
                <option value="">Все категории</option>
                {table.categories.map((category) => (
                    <option key={category.id} value={category.id}>
                        {category.name}
                    </option>
                ))}
            </select>

            <select
                value={table.filters.status}
                aria-label="Статус товара"
                className="h-[42px] min-w-36 rounded-md border border-input bg-background px-3 text-sm font-medium outline-none focus:border-primary"
                onChange={(event) =>
                    visit(table, { status: event.target.value, page: 1 })
                }
            >
                <option value="all">Все статусы</option>
                <option value="active">Активные</option>
                <option value="inactive">Неактивные</option>
            </select>

            <Sheet>
                <SheetTrigger asChild>
                    <Button
                        variant="outline"
                        className="relative justify-start"
                    >
                        <SlidersHorizontal /> Ещё фильтры
                        {(table.filters.stock !== 'all' ||
                            table.filters.performance !== 'all') && (
                            <span className="size-1.5 rounded-full bg-primary" />
                        )}
                    </Button>
                </SheetTrigger>
                <SheetContent side="right">
                    <SheetHeader>
                        <SheetTitle>Дополнительные фильтры</SheetTitle>
                        <SheetDescription>
                            Уточните состояние остатков и динамику продаж.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="space-y-5 px-4">
                        <label className="block text-sm font-semibold">
                            Остатки
                            <select
                                value={stock}
                                onChange={(event) =>
                                    setStock(event.target.value)
                                }
                                className="mt-2 h-[42px] w-full rounded-md border bg-background px-3 font-normal"
                            >
                                <option value="all">Любые</option>
                                <option value="low">Хватит до 7 дней</option>
                                <option value="out">Закончились</option>
                            </select>
                        </label>
                        <label className="block text-sm font-semibold">
                            Динамика
                            <select
                                value={performance}
                                onChange={(event) =>
                                    setPerformance(event.target.value)
                                }
                                className="mt-2 h-[42px] w-full rounded-md border bg-background px-3 font-normal"
                            >
                                <option value="all">Любая</option>
                                <option value="decline">Снижение</option>
                                <option value="growth">Рост</option>
                            </select>
                        </label>
                    </div>
                    <SheetFooter>
                        <Button
                            onClick={() =>
                                visit(table, { stock, performance, page: 1 })
                            }
                        >
                            Применить фильтры
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                visit(table, {
                                    stock: 'all',
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

            <ColumnSettings key={table.columns.join('-')} table={table} />
        </div>
    );
}

function ColumnSettings({ table }: { table: ReadyTable }) {
    const [columns, setColumns] = useState<ProductColumn[]>(table.columns);

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button variant="outline" className="justify-start lg:ml-auto">
                    <Columns3 /> Настроить столбцы
                </Button>
            </SheetTrigger>
            <SheetContent
                side="bottom"
                className="max-h-[85vh] rounded-t-lg md:inset-y-0 md:right-0 md:left-auto md:h-full md:w-[384px] md:rounded-none md:border-l"
            >
                <SheetHeader>
                    <SheetTitle>Столбцы таблицы</SheetTitle>
                    <SheetDescription>
                        Выбор сохранится для этой вкладки и кабинета.
                    </SheetDescription>
                </SheetHeader>
                <div className="space-y-1 px-4">
                    {columnOptions.map((option) => (
                        <label
                            key={option.key}
                            className="flex min-h-11 items-center gap-3 rounded-md px-2 text-sm hover:bg-secondary"
                        >
                            <Checkbox
                                checked={columns.includes(option.key)}
                                onCheckedChange={(checked) =>
                                    setColumns((current) =>
                                        checked
                                            ? [...current, option.key]
                                            : current.filter(
                                                  (column) =>
                                                      column !== option.key,
                                              ),
                                    )
                                }
                            />
                            {option.label}
                        </label>
                    ))}
                </div>
                <SheetFooter>
                    <Button
                        disabled={columns.length === 0}
                        onClick={() =>
                            router.patch('/preferences/product-view', {
                                view: table.view,
                                filters: {
                                    search: table.filters.search || null,
                                    category: table.filters.category,
                                    status: table.filters.status,
                                    stock: table.filters.stock,
                                    performance: table.filters.performance,
                                },
                                columns,
                                sort_column: table.filters.sort,
                                sort_direction: table.filters.direction,
                                page_size: table.pagination.perPage,
                                return_to:
                                    window.location.pathname +
                                    window.location.search,
                            })
                        }
                    >
                        Сохранить вид
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}

function DesktopTable({ table }: { table: ReadyTable }) {
    const has = (column: ProductColumn) => table.columns.includes(column);

    return (
        <div className="hidden overflow-x-auto md:block">
            <table className="w-full min-w-[1040px] border-collapse text-sm">
                <thead className="bg-surface-subtle text-xs text-text-secondary">
                    <tr>
                        <th className="min-w-[300px] px-5 py-3 text-left">
                            <SortButton
                                table={table}
                                column="title"
                                align="left"
                            >
                                Товар
                            </SortButton>
                        </th>
                        {has('revenue') && (
                            <th className="px-3 py-3 text-right">
                                <SortButton table={table} column="revenue">
                                    Выручка
                                </SortButton>
                            </th>
                        )}
                        {has('sales') && (
                            <th className="px-3 py-3 text-right">
                                <SortButton table={table} column="sales">
                                    Продажи
                                </SortButton>
                            </th>
                        )}
                        {has('dynamics') && (
                            <th className="px-3 py-3 text-right">
                                <SortButton table={table} column="dynamics">
                                    Динамика
                                </SortButton>
                            </th>
                        )}
                        {has('buyout') && (
                            <th className="px-3 py-3 text-right">
                                <SortButton table={table} column="buyout">
                                    Выкуп
                                </SortButton>
                            </th>
                        )}
                        {has('returns') && (
                            <th className="px-3 py-3 text-right">
                                <SortButton table={table} column="returns">
                                    Возвраты
                                </SortButton>
                            </th>
                        )}
                        {has('stock') && (
                            <th className="px-3 py-3 text-right">
                                <SortButton table={table} column="stock">
                                    Остаток
                                </SortButton>
                            </th>
                        )}
                        {has('coverage') && (
                            <th className="px-5 py-3 text-right">
                                <SortButton table={table} column="coverage">
                                    Хватит на
                                </SortButton>
                            </th>
                        )}
                    </tr>
                </thead>
                <tbody className="divide-y divide-border-subtle">
                    {table.rows.map((row) => (
                        <tr
                            key={row.id}
                            id={`product-${row.id}`}
                            className="h-[70px] scroll-mt-28 target:bg-accent/60 hover:bg-surface-subtle/70"
                        >
                            <td className="max-w-[360px] px-5 py-2">
                                <Link
                                    href={`/products/${row.id}`}
                                    className="block"
                                >
                                    <ProductIdentity row={row} />
                                </Link>
                            </td>
                            {has('revenue') && (
                                <td className="px-3 py-2 text-right font-medium tabular-nums">
                                    {money.format(row.revenueKopecks / 100)}
                                </td>
                            )}
                            {has('sales') && (
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {row.sales.toLocaleString('ru-RU')}
                                </td>
                            )}
                            {has('dynamics') && (
                                <td
                                    className={cn(
                                        'px-3 py-2 text-right font-medium tabular-nums',
                                        row.dynamics !== null &&
                                            row.dynamics > 0 &&
                                            'text-success',
                                        row.dynamics !== null &&
                                            row.dynamics < 0 &&
                                            'text-destructive',
                                    )}
                                >
                                    {row.dynamics === null
                                        ? '—'
                                        : `${row.dynamics > 0 ? '+' : ''}${row.dynamics}%`}
                                </td>
                            )}
                            {has('buyout') && (
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {row.buyout === null
                                        ? '—'
                                        : `${row.buyout}%`}
                                </td>
                            )}
                            {has('returns') && (
                                <td
                                    className={cn(
                                        'px-3 py-2 text-right tabular-nums',
                                        (row.returnsRate ?? 0) >= 10 &&
                                            'text-warning',
                                    )}
                                >
                                    {row.returnsRate === null
                                        ? '—'
                                        : `${row.returnsRate}%`}
                                </td>
                            )}
                            {has('stock') && (
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {row.stock} шт.
                                </td>
                            )}
                            {has('coverage') && (
                                <td
                                    className={cn(
                                        'px-5 py-2 text-right font-medium tabular-nums',
                                        row.coverage !== null &&
                                            row.coverage <= 7 &&
                                            'text-warning',
                                    )}
                                >
                                    {row.coverage === null
                                        ? '—'
                                        : `${Math.round(row.coverage)} дн.`}
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function MobileRows({ rows }: { rows: ProductAnalyticsRow[] }) {
    return (
        <div className="divide-y divide-border-subtle md:hidden">
            {rows.map((row) => (
                <article key={row.id} className="space-y-3 p-4">
                    <Link href={`/products/${row.id}`} className="block">
                        <ProductIdentity row={row} />
                    </Link>
                    <dl className="grid grid-cols-2 gap-3 rounded-md bg-surface-subtle p-3 text-xs">
                        <div>
                            <dt className="text-muted-foreground">Выручка</dt>
                            <dd className="mt-1 font-semibold">
                                {money.format(row.revenueKopecks / 100)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Динамика</dt>
                            <dd
                                className={cn(
                                    'mt-1 font-semibold',
                                    row.dynamics !== null &&
                                        row.dynamics > 0 &&
                                        'text-success',
                                    row.dynamics !== null &&
                                        row.dynamics < 0 &&
                                        'text-destructive',
                                )}
                            >
                                {row.dynamics === null
                                    ? '—'
                                    : `${row.dynamics > 0 ? '+' : ''}${row.dynamics}%`}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Остаток</dt>
                            <dd className="mt-1 font-semibold">
                                {row.stock} шт.
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Хватит на</dt>
                            <dd
                                className={cn(
                                    'mt-1 font-semibold',
                                    row.coverage !== null &&
                                        row.coverage <= 7 &&
                                        'text-warning',
                                )}
                            >
                                {row.coverage === null
                                    ? '—'
                                    : formatDays(Math.round(row.coverage))}
                            </dd>
                        </div>
                    </dl>
                </article>
            ))}
        </div>
    );
}

function Pagination({ table }: { table: ReadyTable }) {
    const { pagination } = table;
    const start = Math.max(
        1,
        Math.min(pagination.currentPage - 2, pagination.lastPage - 4),
    );
    const pages = Array.from(
        { length: Math.min(5, pagination.lastPage) },
        (_, index) => start + index,
    );

    return (
        <div className="flex flex-col gap-3 border-t border-border-subtle p-4 text-sm sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-2 text-text-secondary">
                <span>Показывать</span>
                <select
                    value={pagination.perPage}
                    aria-label="Строк на странице"
                    className="h-9 rounded-md border bg-background px-2 text-foreground"
                    onChange={(event) =>
                        visit(table, { per_page: event.target.value, page: 1 })
                    }
                >
                    {[25, 50, 100].map((size) => (
                        <option key={size} value={size}>
                            {size}
                        </option>
                    ))}
                </select>
                <span className="hidden sm:inline">из {pagination.total}</span>
            </div>
            <div className="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon-sm"
                    disabled={pagination.currentPage <= 1}
                    aria-label="Предыдущая страница"
                    onClick={() =>
                        visit(table, { page: pagination.currentPage - 1 })
                    }
                >
                    <ChevronLeft />
                </Button>
                {pages.map((page) => (
                    <Button
                        key={page}
                        variant={
                            page === pagination.currentPage
                                ? 'default'
                                : 'ghost'
                        }
                        size="icon-sm"
                        onClick={() => visit(table, { page })}
                    >
                        {page}
                    </Button>
                ))}
                <Button
                    variant="outline"
                    size="icon-sm"
                    disabled={pagination.currentPage >= pagination.lastPage}
                    aria-label="Следующая страница"
                    onClick={() =>
                        visit(table, { page: pagination.currentPage + 1 })
                    }
                >
                    <ChevronRight />
                </Button>
            </div>
        </div>
    );
}

export function ProductTable({ table }: { table: ReadyTable }) {
    return (
        <section className="overflow-hidden rounded-lg border border-border bg-background">
            <Toolbar key={JSON.stringify(table.filters)} table={table} />
            {table.rows.length === 0 ? (
                <div className="flex min-h-[340px] flex-col items-center justify-center px-6 text-center">
                    <span className="grid size-12 place-items-center rounded-full bg-secondary text-text-secondary">
                        <Filter />
                    </span>
                    <h2 className="mt-4 text-base font-semibold">
                        Товары не найдены
                    </h2>
                    <p className="mt-1 max-w-sm text-sm text-text-secondary">
                        Измените запрос или сбросьте фильтры, чтобы увидеть
                        товары.
                    </p>
                    <Button
                        variant="outline"
                        className="mt-4"
                        onClick={() => router.get(routes[table.view])}
                    >
                        Сбросить фильтры
                    </Button>
                </div>
            ) : (
                <>
                    <DesktopTable table={table} />
                    <MobileRows rows={table.rows} />
                    <Pagination table={table} />
                </>
            )}
        </section>
    );
}

export function ProductTabs({ table }: { table: ReadyTable }) {
    const tabs: Array<{ view: ProductView; label: string; count: number }> = [
        { view: 'all', label: 'Все товары', count: table.counts.all },
        {
            view: 'attention',
            label: 'Требуют внимания',
            count: table.counts.attention,
        },
        { view: 'decline', label: 'Снижение', count: table.counts.decline },
        {
            view: 'low_stock',
            label: 'Мало остатков',
            count: table.counts.lowStock,
        },
    ];

    return (
        <nav
            className="flex gap-6 overflow-x-auto border-b border-border-subtle"
            aria-label="Представления товаров"
        >
            {tabs.map((tab) => (
                <Link
                    key={tab.view}
                    href={routes[tab.view]}
                    aria-current={table.view === tab.view ? 'page' : undefined}
                    className={cn(
                        'relative flex min-h-12 shrink-0 items-center gap-2 text-sm font-medium text-text-secondary',
                        table.view === tab.view &&
                            'text-foreground after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:bg-primary',
                    )}
                >
                    {tab.label}
                    <span
                        className={cn(
                            'rounded-full bg-secondary px-2 py-0.5 text-xs',
                            table.view === tab.view && 'bg-accent text-primary',
                        )}
                    >
                        {tab.count}
                    </span>
                </Link>
            ))}
        </nav>
    );
}
