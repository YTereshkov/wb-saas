import { Link } from '@inertiajs/react';
import {
    Boxes,
    ChartNoAxesCombined,
    LayoutDashboard,
    PackageSearch,
    Settings,
} from 'lucide-react';
import type { ComponentType, SVGProps } from 'react';

import { cn } from '@/lib/utils';

import { BrandLogo } from './brand-logo';

type Icon = ComponentType<SVGProps<SVGSVGElement>>;

type NavigationItem = {
    key: string;
    label: string;
    href: string;
    icon: Icon;
};

const navigation: NavigationItem[] = [
    {
        key: 'overview',
        label: 'Обзор',
        href: '/overview',
        icon: LayoutDashboard,
    },
    {
        key: 'products',
        label: 'Товары',
        href: '/products',
        icon: PackageSearch,
    },
    {
        key: 'sales',
        label: 'Продажи',
        href: '/sales',
        icon: ChartNoAxesCombined,
    },
    {
        key: 'stocks',
        label: 'Остатки',
        href: '/stocks',
        icon: Boxes,
    },
];

type AppSidebarProps = {
    activeItem?: string;
    mobile?: boolean;
};

function NavigationLink({
    item,
    active,
    mobile,
}: {
    item: NavigationItem;
    active: boolean;
    mobile: boolean;
}) {
    const Icon = item.icon;

    return (
        <Link
            href={item.href}
            aria-current={active ? 'page' : undefined}
            title={item.label}
            className={cn(
                'flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium text-text-secondary transition-colors outline-none hover:bg-secondary hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/20',
                active && 'bg-accent text-accent-foreground',
                !mobile && 'lg:justify-center lg:px-0 xl:justify-start xl:px-3',
            )}
        >
            <Icon className="size-5 shrink-0" aria-hidden="true" />
            <span className={cn(!mobile && 'lg:hidden xl:inline')}>
                {item.label}
            </span>
        </Link>
    );
}

export function AppSidebar({
    activeItem = 'overview',
    mobile = false,
}: AppSidebarProps) {
    return (
        <div className="flex h-full flex-col bg-background">
            <div
                className={cn(
                    'flex h-[88px] shrink-0 items-center px-6',
                    !mobile &&
                        'lg:justify-center lg:px-0 xl:justify-start xl:px-6',
                )}
            >
                <span className={cn(!mobile && 'lg:hidden xl:inline')}>
                    <BrandLogo href="/overview" />
                </span>
                {!mobile && (
                    <span className="hidden lg:inline xl:hidden">
                        <BrandLogo compact href="/overview" />
                    </span>
                )}
            </div>

            <nav
                className="flex flex-1 flex-col gap-1 px-3 pt-2"
                aria-label="Основная навигация"
            >
                {navigation.map((item) => (
                    <NavigationLink
                        key={item.key}
                        item={item}
                        active={activeItem === item.key}
                        mobile={mobile}
                    />
                ))}
            </nav>

            <div className="space-y-1 border-t border-border-subtle px-3 py-4">
                <NavigationLink
                    item={{
                        key: 'settings',
                        label: 'Настройки',
                        href: '/settings/cabinets',
                        icon: Settings,
                    }}
                    active={activeItem === 'settings'}
                    mobile={mobile}
                />
            </div>
        </div>
    );
}
