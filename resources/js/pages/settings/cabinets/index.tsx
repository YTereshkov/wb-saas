import { Head, Link, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronRight,
    CircleAlert,
    Plus,
    RefreshCw,
    Store,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { SettingsLayout } from '@/layouts/settings-layout';
import { cn } from '@/lib/utils';

type Cabinet = {
    id: number;
    name: string;
    wbAccountId: string | null;
    environment: string;
    status: string;
    statusLabel: string;
    lastSyncAt: string | null;
    lastSyncLabel: string;
    permissions: string[];
    syncProgress: number | null;
};

const permissionLabels: Record<string, string> = {
    products: 'Товары',
    orders: 'Заказы',
    sales: 'Продажи',
    stocks: 'Остатки',
};

const cabinetPluralRules = new Intl.PluralRules('ru-RU');

function cabinetCountLabel(count: number) {
    const form = cabinetPluralRules.select(count);

    return form === 'one'
        ? 'кабинет'
        : form === 'few'
          ? 'кабинета'
          : 'кабинетов';
}

export default function CabinetIndex({
    cabinets,
}: {
    cabinets: { items: Cabinet[]; count: number };
}) {
    const [cabinetToDelete, setCabinetToDelete] = useState<Cabinet | null>(
        null,
    );
    const deleteForm = useForm({});

    return (
        <>
            <Head title="Кабинеты" />
            <SettingsLayout activeTab="cabinets">
                <Card className="gap-0 overflow-hidden py-0">
                    <div className="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-baseline gap-3">
                            <h2 className="text-lg font-semibold">
                                Подключённые кабинеты
                            </h2>
                            <span className="text-sm text-text-secondary">
                                {cabinets.count}{' '}
                                {cabinetCountLabel(cabinets.count)}
                            </span>
                        </div>
                        <Button asChild>
                            <Link href="/settings/cabinets/connect">
                                <Plus />
                                Подключить кабинет
                            </Link>
                        </Button>
                    </div>

                    {cabinets.items.length === 0 ? (
                        <div className="border-t border-border-subtle px-6 py-14 text-center">
                            <Store className="mx-auto size-10 text-muted-foreground" />
                            <h3 className="mt-4 font-semibold">
                                Пока нет подключённых кабинетов
                            </h3>
                            <p className="mt-1 text-sm text-text-secondary">
                                Добавьте demo-кабинет, чтобы начать первичную
                                загрузку.
                            </p>
                        </div>
                    ) : (
                        <div className="border-t border-border-subtle">
                            <div className="hidden grid-cols-[1.3fr_.7fr_.9fr_1fr_auto] gap-5 border-b border-border-subtle px-6 py-3 text-xs text-text-secondary md:grid">
                                <span>Кабинет</span>
                                <span>Статус</span>
                                <span>Последнее обновление</span>
                                <span>Данные</span>
                                <span className="w-[76px]" />
                            </div>
                            {cabinets.items.map((cabinet) => (
                                <div
                                    key={cabinet.id}
                                    className="grid gap-4 border-b border-border-subtle px-6 py-4 last:border-b-0 md:grid-cols-[1.3fr_.7fr_.9fr_1fr_auto] md:items-center md:gap-5"
                                >
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-12 shrink-0 items-center justify-center rounded-lg border border-border text-text-secondary">
                                            <Store />
                                        </span>
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-semibold">
                                                    {cabinet.name}
                                                </p>
                                                {cabinet.environment ===
                                                    'sandbox' && (
                                                    <Badge variant="secondary">
                                                        Песочница WB
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="mt-0.5 text-xs text-text-secondary">
                                                {cabinet.wbAccountId
                                                    ? `WB ID ${cabinet.wbAccountId}`
                                                    : 'WB ID не указан'}
                                            </p>
                                        </div>
                                    </div>
                                    <Badge
                                        variant={
                                            cabinet.status === 'active'
                                                ? 'success'
                                                : cabinet.status ===
                                                    'initial_sync'
                                                  ? 'info'
                                                  : 'secondary'
                                        }
                                        className="w-fit"
                                    >
                                        {cabinet.status === 'active' ? (
                                            <CheckCircle2 />
                                        ) : cabinet.status ===
                                          'initial_sync' ? (
                                            <RefreshCw className="animate-spin" />
                                        ) : (
                                            <CircleAlert />
                                        )}
                                        {cabinet.statusLabel}
                                    </Badge>
                                    <div className="text-sm">
                                        <p>{cabinet.lastSyncLabel}</p>
                                        {cabinet.status === 'initial_sync' && (
                                            <p className="mt-1 text-xs text-primary">
                                                Загружено{' '}
                                                {cabinet.syncProgress ?? 0}%
                                            </p>
                                        )}
                                    </div>
                                    <p className="text-sm text-text-secondary">
                                        {cabinet.permissions.length > 0
                                            ? cabinet.permissions
                                                  .map(
                                                      (permission) =>
                                                          permissionLabels[
                                                              permission
                                                          ] ?? permission,
                                                  )
                                                  .join(', ')
                                            : 'Ожидаем доступы'}
                                    </p>
                                    <div className="flex items-center justify-end gap-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            className="text-text-secondary hover:text-destructive"
                                            aria-label={`Удалить ${cabinet.name}`}
                                            onClick={() =>
                                                setCabinetToDelete(cabinet)
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label={`Открыть ${cabinet.name}`}
                                        >
                                            <Link
                                                href={
                                                    cabinet.status ===
                                                    'verified'
                                                        ? `/settings/cabinets/${cabinet.id}/verification`
                                                        : cabinet.status ===
                                                            'initial_sync'
                                                          ? `/settings/cabinets/${cabinet.id}/initial-sync`
                                                          : `/settings/cabinets/${cabinet.id}`
                                                }
                                            >
                                                <ChevronRight />
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>

                <div
                    className={cn(
                        'mt-6 grid gap-5 rounded-xl border border-border p-6 lg:grid-cols-2',
                    )}
                >
                    <div>
                        <h2 className="text-lg font-semibold">
                            Подключить ещё один кабинет
                        </h2>
                        <p className="mt-1 text-sm text-text-secondary">
                            Для Demo-MVP используется безопасный локальный
                            токен.
                        </p>
                    </div>
                    <div className="text-sm text-text-secondary">
                        <p className="font-medium text-foreground">
                            Безопасность подключения
                        </p>
                        <p className="mt-2">
                            Токен хранится в зашифрованном виде и не передаётся
                            в браузер после сохранения.
                        </p>
                    </div>
                </div>

                <AlertDialog
                    open={cabinetToDelete !== null}
                    onOpenChange={(open) => {
                        if (!open && !deleteForm.processing) {
                            setCabinetToDelete(null);
                        }
                    }}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Удалить «{cabinetToDelete?.name}»?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Кабинет, токен и все загруженные данные будут
                                удалены без возможности восстановления.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={deleteForm.processing}>
                                Отмена
                            </AlertDialogCancel>
                            <AlertDialogAction
                                className={buttonVariants({
                                    variant: 'destructive',
                                })}
                                disabled={deleteForm.processing}
                                onClick={(event) => {
                                    event.preventDefault();

                                    if (cabinetToDelete === null) {
                                        return;
                                    }

                                    deleteForm.delete(
                                        `/settings/cabinets/${cabinetToDelete.id}`,
                                        {
                                            preserveScroll: true,
                                            onSuccess: () =>
                                                setCabinetToDelete(null),
                                        },
                                    );
                                }}
                            >
                                <Trash2 />
                                {deleteForm.processing
                                    ? 'Удаляем...'
                                    : 'Удалить'}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </SettingsLayout>
        </>
    );
}
