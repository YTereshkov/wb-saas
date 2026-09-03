import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { cn } from '@/lib/utils';
import type { ProductDetailsData } from '@/types';

export function ProductHeader({
    product,
    active,
}: {
    product: ProductDetailsData['product'];
    active: 'overview' | 'sales' | 'stocks';
}) {
    const tabs = [
        { key: 'overview', label: 'Обзор', href: `/products/${product.id}` },
        {
            key: 'sales',
            label: 'Продажи и выкуп',
            href: `/products/${product.id}/sales`,
        },
        {
            key: 'stocks',
            label: 'Остатки',
            href: `/products/${product.id}/stocks`,
        },
    ] as const;

    return (
        <header>
            <Link
                href="/products"
                className="inline-flex items-center gap-1.5 text-xs font-medium text-primary"
            >
                <ArrowLeft className="size-3.5" /> Все товары
            </Link>
            <div className="mt-3 flex items-center gap-4">
                <div className="size-16 overflow-hidden rounded-md border bg-surface-subtle sm:size-[72px]">
                    {product.imageUrl && (
                        <img
                            src={product.imageUrl}
                            alt=""
                            className="size-full object-cover"
                        />
                    )}
                </div>
                <div className="min-w-0">
                    <h1 className="truncate text-[24px] leading-8 font-semibold">
                        {product.title}
                    </h1>
                    <p className="mt-1 text-xs text-text-secondary">
                        Арт. {product.vendorCode} · nmID {product.nmId}
                    </p>
                </div>
            </div>
            <nav
                className="mt-4 flex gap-7 overflow-x-auto border-b"
                aria-label="Разделы товара"
            >
                {tabs.map((tab) => (
                    <Link
                        key={tab.key}
                        href={tab.href}
                        aria-current={active === tab.key ? 'page' : undefined}
                        className={cn(
                            'shrink-0 border-b-2 px-1 pb-3 text-sm font-medium',
                            active === tab.key
                                ? 'border-primary text-primary'
                                : 'border-transparent text-text-secondary hover:text-foreground',
                        )}
                    >
                        {tab.label}
                    </Link>
                ))}
            </nav>
        </header>
    );
}
