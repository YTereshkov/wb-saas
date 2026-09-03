import { Link } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import { cn } from '@/lib/utils';

export function SalesHeader({
    active,
}: {
    active: 'dynamics' | 'orders-sales' | 'buyout-returns' | 'products';
}) {
    const tabsRef = useRef<HTMLElement>(null);
    const tabs = [
        { key: 'dynamics', label: 'Динамика', href: '/sales' },
        {
            key: 'orders-sales',
            label: 'Заказы и продажи',
            href: '/sales/orders-sales',
        },
        {
            key: 'buyout-returns',
            label: 'Выкуп и возвраты',
            href: '/sales/buyout-returns',
        },
        { key: 'products', label: 'По товарам', href: '/sales/products' },
    ];

    useEffect(() => {
        tabsRef.current
            ?.querySelector('[aria-current="page"]')
            ?.scrollIntoView({ block: 'nearest', inline: 'center' });
    }, [active]);

    return (
        <header>
            <h1 className="text-[30px] leading-[38px] font-semibold">
                Продажи
            </h1>
            <p className="mt-1 text-sm text-text-secondary">
                Динамика и качество продаж за выбранный период
            </p>
            <nav
                ref={tabsRef}
                className="mt-4 flex gap-7 overflow-x-auto border-b"
                aria-label="Разделы продаж"
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
                                : 'border-transparent text-text-secondary',
                        )}
                    >
                        {tab.label}
                    </Link>
                ))}
            </nav>
        </header>
    );
}
