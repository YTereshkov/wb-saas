import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';

import { cn } from '@/lib/utils';

import { AppLayout } from './app-layout';

const settingsTabs = [
    { key: 'cabinets', label: 'Кабинеты', href: '/settings/cabinets' },
    {
        key: 'notifications',
        label: 'Уведомления',
        href: '/settings/notifications',
    },
    { key: 'profile', label: 'Профиль', href: '/settings/profile' },
] as const;

type SettingsLayoutProps = PropsWithChildren<{
    activeTab?: (typeof settingsTabs)[number]['key'];
}>;

export function SettingsLayout({
    activeTab = 'cabinets',
    children,
}: SettingsLayoutProps) {
    return (
        <AppLayout activeItem="settings" settings>
            <div className="mx-auto max-w-[1180px]">
                <h1 className="text-[30px] leading-[38px] font-semibold tracking-normal">
                    Настройки
                </h1>
                <p className="mt-1 text-sm text-text-secondary">
                    Подключения и параметры вашего аккаунта
                </p>

                <nav
                    className="mt-6 flex gap-4 overflow-x-auto border-b border-border-subtle sm:gap-6"
                    aria-label="Разделы настроек"
                >
                    {settingsTabs.map((tab) => (
                        <Link
                            key={tab.key}
                            href={tab.href}
                            aria-current={
                                activeTab === tab.key ? 'page' : undefined
                            }
                            className={cn(
                                'relative flex min-h-11 items-center px-1 text-sm font-medium text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/20',
                                activeTab === tab.key && 'text-foreground',
                            )}
                        >
                            {tab.label}
                            {activeTab === tab.key && (
                                <span className="absolute inset-x-0 bottom-[-1px] h-0.5 bg-primary" />
                            )}
                        </Link>
                    ))}
                </nav>

                <div className="pt-7">{children}</div>
            </div>
        </AppLayout>
    );
}
