import { Head, Link } from '@inertiajs/react';
import { PackageSearch, Plus } from 'lucide-react';

import { ProductTable, ProductTabs } from '@/components/products/product-table';
import { Button } from '@/components/ui/button';
import { AppLayout } from '@/layouts/app-layout';
import type { ProductTableData } from '@/types';

const subtitles = {
    all: 'Полная картина продаж, выкупа и остатков',
    attention: 'Товары с главным сигналом, отсортированные по приоритету',
    decline: 'Товары, у которых выручка снизилась к прошлому периоду',
    low_stock: 'Товары, запас которых рассчитан максимум на 7 дней',
};

export default function ProductsIndex({ table }: { table: ProductTableData }) {
    if (
        table.state === 'empty' ||
        !table.rows ||
        !table.counts ||
        !table.pagination ||
        !table.filters ||
        !table.columns ||
        !table.categories
    ) {
        return (
            <>
                <Head title="Товары" />
                <AppLayout activeItem="products">
                    <div className="mx-auto max-w-[1320px]">
                        <h1 className="text-[30px] leading-[38px] font-semibold tracking-normal">
                            Товары
                        </h1>
                        <div className="mt-6 flex min-h-[420px] flex-col items-center justify-center rounded-lg border border-dashed bg-surface-subtle px-6 text-center">
                            <PackageSearch className="size-10 text-muted-foreground" />
                            <h2 className="mt-4 text-lg font-semibold">
                                Сначала подключите кабинет
                            </h2>
                            <p className="mt-2 max-w-md text-sm text-text-secondary">
                                Каталог появится после первичной синхронизации.
                            </p>
                            <Button asChild className="mt-5">
                                <Link href="/settings/cabinets/connect">
                                    <Plus /> Подключить кабинет
                                </Link>
                            </Button>
                        </div>
                    </div>
                </AppLayout>
            </>
        );
    }

    const readyTable = {
        ...table,
        rows: table.rows,
        counts: table.counts,
        pagination: table.pagination,
        filters: table.filters,
        columns: table.columns,
        categories: table.categories,
    };

    return (
        <>
            <Head title="Товары" />
            <AppLayout activeItem="products">
                <div className="mx-auto max-w-[1320px]">
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                        <div>
                            <h1 className="text-[30px] leading-[38px] font-semibold tracking-normal">
                                Товары
                            </h1>
                            <p className="mt-1 text-sm text-text-secondary">
                                {readyTable.pagination.total} товаров ·{' '}
                                {subtitles[readyTable.view]}
                            </p>
                        </div>
                        {readyTable.isSaved && (
                            <span className="w-fit rounded-full bg-success-soft px-3 py-1.5 text-xs font-semibold text-success">
                                Сохранённый вид
                            </span>
                        )}
                    </div>
                    <div className="mt-5">
                        <ProductTabs table={readyTable} />
                    </div>
                    <div className="mt-5">
                        <ProductTable table={readyTable} />
                    </div>
                </div>
            </AppLayout>
        </>
    );
}
