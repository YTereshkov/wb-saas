import { Link, router } from '@inertiajs/react';
import {
    CircleAlert,
    CloudOff,
    KeyRound,
    PackageOpen,
    RefreshCw,
    TriangleAlert,
} from 'lucide-react';
import type { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { AnalyticsContext } from '@/types';

export function SectionSkeleton({ rows = 3 }: { rows?: number }) {
    return (
        <div
            className="rounded-lg border border-border p-5"
            aria-busy="true"
            aria-label="Загрузка данных"
        >
            <Skeleton className="h-5 w-48" />
            <div className="mt-5 grid gap-3">
                {Array.from({ length: rows }, (_, index) => (
                    <Skeleton key={index} className="h-12 w-full" />
                ))}
            </div>
        </div>
    );
}

export function EmptyState({
    title,
    description,
    filtered = false,
    children,
}: {
    title: string;
    description: string;
    filtered?: boolean;
    children?: ReactNode;
}) {
    return (
        <div className="flex min-h-64 flex-col items-center justify-center rounded-lg border border-dashed border-border bg-surface-subtle px-6 text-center">
            <PackageOpen className="size-9 text-muted-foreground" />
            <h3 className="mt-4 font-semibold">
                {filtered ? 'По фильтрам ничего не найдено' : title}
            </h3>
            <p className="mt-1 max-w-md text-sm text-text-secondary">
                {filtered
                    ? 'Измените условия или сбросьте фильтры.'
                    : description}
            </p>
            {children && <div className="mt-4">{children}</div>}
        </div>
    );
}

export function SectionError({
    title = 'Не удалось загрузить раздел',
    onRetry,
}: {
    title?: string;
    onRetry?: () => void;
}) {
    return (
        <div
            role="alert"
            className="rounded-lg border border-destructive/25 bg-danger-soft p-5"
        >
            <div className="flex gap-3">
                <CircleAlert className="size-5 shrink-0 text-destructive" />
                <div>
                    <h3 className="font-semibold">{title}</h3>
                    <p className="mt-1 text-sm text-text-secondary">
                        Остальные данные доступны. Повторите загрузку этой
                        секции.
                    </p>
                </div>
            </div>
            <Button
                className="mt-4"
                variant="outline"
                onClick={onRetry ?? (() => router.reload())}
            >
                <RefreshCw />
                Повторить
            </Button>
        </div>
    );
}

function Notice({
    tone,
    icon,
    title,
    description,
    action,
}: {
    tone: 'warning' | 'danger' | 'info';
    icon: ReactNode;
    title: string;
    description: string;
    action?: ReactNode;
}) {
    const colors =
        tone === 'danger'
            ? 'border-destructive/25 bg-danger-soft'
            : tone === 'warning'
              ? 'border-warning/25 bg-warning-soft'
              : 'border-info/20 bg-info-soft';

    return (
        <div
            role="status"
            className={`flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center ${colors}`}
        >
            <span className="shrink-0">{icon}</span>
            <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold">{title}</p>
                <p className="mt-0.5 text-sm text-text-secondary">
                    {description}
                </p>
            </div>
            {action}
        </div>
    );
}

export function DataAvailabilityNotices({
    context,
    activeItem,
    dataResource,
}: {
    context: AnalyticsContext | null;
    activeItem?: string;
    dataResource?: 'stocks';
}) {
    const sync = context?.sync;
    const account = context?.activeAccount;
    const period = context?.period;
    const stockCoverage = context?.coverage.resources.stocks;
    const stockStart = stockCoverage?.start ?? null;
    const stockEnd = stockCoverage?.end ?? null;
    const stockRangeSelected =
        (activeItem === 'stocks' || dataResource === 'stocks') && period;
    const stockDataMissing =
        stockRangeSelected &&
        (!stockStart ||
            !stockEnd ||
            period.end < stockStart ||
            period.start > stockEnd);
    const stockDataPartial =
        stockRangeSelected &&
        !stockDataMissing &&
        stockStart &&
        stockEnd &&
        (period.start < stockStart || period.end > stockEnd);

    if (!sync || !account) {
        return null;
    }

    return (
        <div className="mx-auto mb-5 grid max-w-[1320px] gap-3">
            {stockDataMissing ? (
                <Notice
                    tone="info"
                    icon={<PackageOpen className="size-5 text-info" />}
                    title="За выбранный период нет данных об остатках"
                    description={
                        stockStart && stockEnd
                            ? `SellerScope хранит историю остатков с ${stockStart.split('-').reverse().join('.')} по ${stockEnd.split('-').reverse().join('.')}. Данные о продажах за выбранные даты остаются доступны.`
                            : 'SellerScope ещё не получил ни одного снимка остатков. Данные о продажах за выбранные даты остаются доступны.'
                    }
                />
            ) : stockDataPartial ? (
                <Notice
                    tone="info"
                    icon={<PackageOpen className="size-5 text-info" />}
                    title="Данные об остатках доступны только для части периода"
                    description={`История остатков доступна с ${stockStart?.split('-').reverse().join('.')} по ${stockEnd?.split('-').reverse().join('.')}. Показатели остатков рассчитаны только по этой части диапазона.`}
                />
            ) : null}
            {account.status === 'disconnected' && (
                <Notice
                    tone="danger"
                    icon={<CloudOff className="size-5 text-destructive" />}
                    title="Кабинет отключён"
                    description="Синхронизация остановлена. Подключите токен, чтобы получать новые данные."
                    action={
                        <Button asChild size="sm" variant="outline">
                            <Link href={`/settings/cabinets/${account.id}`}>
                                Открыть настройки
                            </Link>
                        </Button>
                    }
                />
            )}
            {account.status === 'invalid_credentials' && (
                <Notice
                    tone="danger"
                    icon={<KeyRound className="size-5 text-destructive" />}
                    title="Токен Wildberries недействителен"
                    description="Сохранённые показатели доступны, но новые данные не загружаются."
                    action={
                        <Button asChild size="sm" variant="outline">
                            <Link href={`/settings/cabinets/${account.id}`}>
                                Обновить токен
                            </Link>
                        </Button>
                    }
                />
            )}
            {(account.status === 'partial' ||
                sync.unavailableResources.length > 0) && (
                <Notice
                    tone="warning"
                    icon={<TriangleAlert className="size-5 text-warning" />}
                    title="Данные загружены частично"
                    description={`Недоступные разделы: ${sync.unavailableResources.join(', ') || 'часть данных'}. Показатели могут быть неполными.`}
                />
            )}
            {sync.missingPermissions.length > 0 && (
                <Notice
                    tone="warning"
                    icon={<KeyRound className="size-5 text-warning" />}
                    title="Не хватает доступов Wildberries"
                    description={`Токен не даёт доступ к разделам: ${sync.missingPermissions.join(', ')}.`}
                />
            )}
            {sync.isStale && account.status === 'active' && (
                <Notice
                    tone="info"
                    icon={<RefreshCw className="size-5 text-info" />}
                    title="Данные давно не обновлялись"
                    description={`${sync.label}. Запустите синхронизацию в настройках кабинета.`}
                />
            )}
        </div>
    );
}
