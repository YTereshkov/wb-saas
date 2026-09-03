import { router, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronDown,
    CircleAlert,
    Menu,
    RefreshCw,
} from 'lucide-react';
import { useState } from 'react';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { AnalyticsPeriodControl } from '@/features/analytics-context/analytics-period-control';
import type { AnalyticsContext } from '@/types';

import { AppSidebar } from './app-sidebar';
import { BrandLogo } from './brand-logo';

type AppTopbarProps = {
    activeItem?: string;
    settings?: boolean;
};

function ContextMenu({
    label,
    icon,
    children,
}: {
    label: string;
    icon?: React.ReactNode;
    children: (close: () => void) => React.ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <div className="relative">
            <Button
                type="button"
                variant="outline"
                className="max-w-[250px] justify-between gap-3 font-medium"
                aria-expanded={open}
                onClick={() => setOpen((value) => !value)}
            >
                {icon}
                <span className="truncate">{label}</span>
                <ChevronDown className="text-muted-foreground" />
            </Button>
            {open && (
                <div className="absolute top-[calc(100%+8px)] left-0 z-50 min-w-full overflow-hidden rounded-lg border border-border bg-background p-1 shadow-lg">
                    {children(() => setOpen(false))}
                </div>
            )}
        </div>
    );
}

function ContextOption({
    active,
    children,
    onSelect,
}: {
    active: boolean;
    children: React.ReactNode;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            className="flex min-h-10 w-full items-center rounded-md px-3 text-left text-sm whitespace-nowrap hover:bg-secondary data-[active=true]:bg-accent data-[active=true]:text-accent-foreground"
            data-active={active}
            onClick={onSelect}
        >
            {children}
        </button>
    );
}

function updateContext(
    context: AnalyticsContext,
    values: {
        sellerAccountId?: number;
        periodPreset?: string;
        periodStart?: string;
        periodEnd?: string;
    },
    onFailure: () => void,
) {
    router.patch(
        '/preferences/analytics-context',
        {
            seller_account_id:
                values.sellerAccountId ?? context.activeAccount?.id ?? null,
            period_preset: values.periodPreset ?? context.period.preset,
            period_start: values.periodStart,
            period_end: values.periodEnd,
            return_to: window.location.pathname + window.location.search,
        },
        {
            onError: onFailure,
            onHttpException: onFailure,
        },
    );
}

export function AppTopbar({
    activeItem = 'overview',
    settings = false,
}: AppTopbarProps) {
    const page = usePage();
    const [failedUpdate, setFailedUpdate] = useState<{
        sellerAccountId?: number;
        periodPreset?: string;
        periodStart?: string;
        periodEnd?: string;
    } | null>(null);
    const context = page.props.analyticsContext;
    const user = page.props.auth.user;
    const initials = user?.name
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
    const changeContext = (values: {
        sellerAccountId?: number;
        periodPreset?: string;
        periodStart?: string;
        periodEnd?: string;
    }) => {
        if (!context) {
            return;
        }

        setFailedUpdate(null);
        updateContext(context, values, () => setFailedUpdate(values));
    };

    return (
        <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-border-subtle bg-background px-4 lg:h-[88px] lg:px-7 xl:px-8">
            {failedUpdate && (
                <div
                    role="alert"
                    className="absolute top-full right-4 left-4 flex items-center gap-3 border border-destructive/30 bg-background px-4 py-3 text-sm shadow-lg sm:left-auto sm:max-w-md"
                >
                    <CircleAlert className="size-4 shrink-0 text-destructive" />
                    <span>Не удалось изменить контекст аналитики.</span>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="ml-auto"
                        onClick={() => changeContext(failedUpdate)}
                    >
                        Повторить
                    </Button>
                </div>
            )}
            <div className="flex min-w-0 items-center gap-3">
                <Sheet>
                    <SheetTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="lg:hidden"
                            aria-label="Открыть меню"
                        >
                            <Menu />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" className="p-0">
                        <SheetTitle className="sr-only">
                            Навигация SellerScope
                        </SheetTitle>
                        <SheetDescription className="sr-only">
                            Основные разделы сервиса
                        </SheetDescription>
                        <AppSidebar activeItem={activeItem} mobile />
                    </SheetContent>
                </Sheet>

                <span className="lg:hidden">
                    <BrandLogo href="/overview" />
                </span>

                {context && context.accounts.length > 0 && (
                    <div className="hidden items-center gap-3 md:flex">
                        <span className="text-sm text-text-secondary">
                            Кабинет:
                        </span>
                        <ContextMenu
                            label={
                                context.activeAccount?.name ??
                                'Выберите кабинет'
                            }
                        >
                            {(close) =>
                                context.accounts.map((account) => (
                                    <ContextOption
                                        key={account.id}
                                        active={
                                            context.activeAccount?.id ===
                                            account.id
                                        }
                                        onSelect={() => {
                                            close();
                                            changeContext({
                                                sellerAccountId: account.id,
                                            });
                                        }}
                                    >
                                        {account.name}
                                    </ContextOption>
                                ))
                            }
                        </ContextMenu>
                    </div>
                )}

                {!settings && context?.sync && (
                    <div className="hidden items-center gap-2 text-xs text-muted-foreground xl:flex">
                        <CheckCircle2
                            className="size-4 text-success"
                            aria-hidden="true"
                        />
                        {context.sync.label}
                    </div>
                )}
            </div>

            {settings ? (
                <div className="flex min-h-11 items-center gap-3 rounded-lg px-2 text-left">
                    <Avatar className="size-9">
                        <AvatarFallback className="bg-accent font-semibold text-accent-foreground">
                            {initials || 'SS'}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden sm:block">
                        <span className="block text-sm font-semibold">
                            {user?.name}
                        </span>
                        <span className="block text-xs text-muted-foreground">
                            Владелец
                        </span>
                    </span>
                </div>
            ) : (
                <div className="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="hidden text-muted-foreground sm:inline-flex"
                        aria-label="Обновить данные"
                        disabled
                    >
                        <RefreshCw />
                    </Button>
                    {context && (
                        <AnalyticsPeriodControl
                            context={context}
                            onChange={changeContext}
                        />
                    )}
                    {context && (
                        <Button
                            variant="outline"
                            className="hidden font-medium xl:flex"
                            disabled
                        >
                            {context.comparison.isComplete
                                ? `Сравнить: ${context.comparison.label}`
                                : 'Сравнение недоступно'}
                        </Button>
                    )}
                    <Avatar className="size-9">
                        <AvatarFallback className="bg-accent font-semibold text-accent-foreground">
                            {initials || 'SS'}
                        </AvatarFallback>
                    </Avatar>
                </div>
            )}
        </header>
    );
}
