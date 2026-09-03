import type { PropsWithChildren } from 'react';

import { BrandLogo } from '@/components/brand-logo';
import { Badge } from '@/components/ui/badge';

function AuthProductPreview() {
    return (
        <div className="w-full rounded-xl border border-border bg-background">
            <div className="p-5 xl:p-6">
                <div className="flex items-center justify-between gap-4">
                    <p className="text-base font-semibold">
                        Сегодня в магазине
                    </p>
                    <div className="flex items-center gap-2 text-xs text-text-secondary">
                        <span className="size-2 rounded-full bg-success" />
                        Данные актуальны
                    </div>
                </div>

                <div className="mt-6 grid grid-cols-3 divide-x divide-border">
                    <div className="pr-5">
                        <p className="text-xs text-text-secondary">Выручка</p>
                        <p className="mt-2 text-xl font-semibold">
                            1 284 600 ₽
                        </p>
                        <Badge variant="success" className="mt-2 px-0">
                            +12,4%
                        </Badge>
                    </div>
                    <div className="px-5">
                        <p className="text-xs text-text-secondary">Заказы</p>
                        <p className="mt-2 text-xl font-semibold">1 842</p>
                        <Badge variant="success" className="mt-2 px-0">
                            +8,7%
                        </Badge>
                    </div>
                    <div className="pl-5">
                        <p className="text-xs text-text-secondary">Выкуп</p>
                        <p className="mt-2 text-xl font-semibold">79,9%</p>
                        <Badge variant="success" className="mt-2 px-0">
                            +1,1 п.п.
                        </Badge>
                    </div>
                </div>

                <div className="pt-6">
                    <svg
                        viewBox="0 0 540 150"
                        className="h-auto w-full overflow-visible"
                        role="img"
                        aria-label="Демонстрационный график роста продаж"
                    >
                        <g stroke="#ECEEF2" strokeWidth="1">
                            <line x1="0" x2="540" y1="20" y2="20" />
                            <line x1="0" x2="540" y1="75" y2="75" />
                            <line x1="0" x2="540" y1="130" y2="130" />
                        </g>
                        <path
                            d="M0 120 C48 80 57 112 98 88 S157 100 198 52 S255 68 292 96 S348 104 390 68 S448 24 540 60"
                            fill="none"
                            stroke="#5527FF"
                            strokeLinecap="round"
                            strokeWidth="3"
                        />
                    </svg>
                </div>
            </div>

            <div className="flex items-center justify-between gap-4 border-t border-border-subtle px-5 py-4 xl:px-6">
                <div className="flex min-w-0 items-center gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-warning-soft font-semibold text-warning">
                        !
                    </div>
                    <p className="truncate text-xs font-medium">
                        Подушка Memory закончится примерно через 4 дня
                    </p>
                </div>
                <span className="shrink-0 text-xs font-medium text-primary">
                    Проверить остатки →
                </span>
            </div>
        </div>
    );
}

export function AuthLayout({ children }: PropsWithChildren) {
    return (
        <div className="min-h-screen bg-background lg:grid lg:grid-cols-[57%_43%]">
            <section className="relative hidden min-h-screen overflow-hidden border-r border-border-subtle bg-surface-subtle p-10 lg:flex lg:flex-col">
                <BrandLogo />
                <div className="mx-auto mt-[78px] w-full max-w-[686px]">
                    <p className="text-sm font-medium text-text-secondary">
                        Аналитика для продавцов Wildberries
                    </p>
                    <h2 className="mt-3 max-w-[570px] text-[30px] leading-[38px] font-semibold tracking-normal xl:text-[36px] xl:leading-[44px]">
                        Понимайте не только цифры,
                        <br />и что делать дальше
                    </h2>
                    <p className="mt-3 max-w-[580px] text-sm leading-5 text-text-secondary xl:text-base xl:leading-6">
                        Продажи, остатки и товары, требующие внимания — в одном
                        понятном сервисе.
                    </p>
                    <div className="mt-7">
                        <AuthProductPreview />
                    </div>
                </div>
                <p className="mt-auto text-xs text-text-secondary">
                    SellerScope не является официальным продуктом Wildberries.
                </p>
            </section>

            <main className="flex min-h-screen justify-center px-5 py-10 sm:px-8 lg:items-start lg:px-12 lg:pt-[158px]">
                <div className="w-full max-w-[448px]">
                    <div className="mb-12 lg:hidden">
                        <BrandLogo />
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
