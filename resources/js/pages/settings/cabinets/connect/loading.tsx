import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleAlert,
    Clock3,
    LoaderCircle,
    RotateCcw,
    Store,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import { ConnectionShell } from '@/components/settings/connection-shell';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type SyncStatus = {
    account: {
        id: number;
        name: string;
        wbAccountId: string;
        status: string;
        environment: string;
    };
    run: {
        id: number;
        status: string;
        progress: number;
        errorSummary: string | null;
    } | null;
    resources: Array<{
        key: string;
        label: string;
        status: string;
        availability: string;
    }>;
    completed: boolean;
};

export default function ConnectionLoading({
    syncStatus,
}: {
    syncStatus: SyncStatus;
}) {
    const [retrying, setRetrying] = useState(false);

    useEffect(() => {
        if (syncStatus.completed || syncStatus.run?.status === 'failed') {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['syncStatus'] });
        }, 1500);

        return () => window.clearInterval(timer);
    }, [syncStatus.completed, syncStatus.run?.status]);

    const progress = syncStatus.run?.progress ?? 0;
    const failed = syncStatus.run?.status === 'failed';
    const partial = syncStatus.account.status === 'partial';
    const sandbox = syncStatus.account.environment === 'sandbox';

    return (
        <>
            <Head title="Загрузка данных" />
            <ConnectionShell current={3}>
                <div className="grid lg:grid-cols-[1.2fr_.95fr]">
                    <div className="p-6 lg:p-7">
                        <div className="flex items-center gap-4">
                            {syncStatus.completed && (!partial || sandbox) ? (
                                <CheckCircle2 className="size-10 text-success" />
                            ) : syncStatus.completed ? (
                                <CircleAlert className="size-10 text-warning" />
                            ) : failed ? (
                                <CircleAlert className="size-10 text-destructive" />
                            ) : (
                                <LoaderCircle className="size-10 animate-spin text-primary" />
                            )}
                            <div>
                                <h3 className="text-xl font-semibold">
                                    {syncStatus.completed
                                        ? sandbox
                                            ? 'Данные песочницы загружены'
                                            : partial
                                              ? 'Данные загружены частично'
                                              : 'Данные кабинета загружены'
                                        : failed
                                          ? 'Загрузка остановлена'
                                          : 'Загружаем данные кабинета'}
                                </h3>
                                <p className="mt-1 text-sm text-text-secondary">
                                    {syncStatus.account.name} · WB ID{' '}
                                    {syncStatus.account.wbAccountId}
                                </p>
                            </div>
                        </div>

                        <p className="mt-5 text-3xl font-semibold text-primary">
                            {progress}%
                        </p>
                        <div className="mt-2 h-2 overflow-hidden rounded-full bg-[#e8e7f0]">
                            <div
                                className="h-full rounded-full bg-primary transition-[width] duration-500"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        <p className="mt-2 text-sm text-text-secondary">
                            {syncStatus.completed
                                ? sandbox
                                    ? 'Товары, заказы и продажи загружены'
                                    : partial
                                      ? 'Доступные разделы успешно загружены'
                                      : 'Первичная загрузка завершена'
                                : failed
                                  ? (syncStatus.run?.errorSummary ??
                                    'Не удалось завершить первичную загрузку')
                                  : 'Первичная загрузка выполняется в фоне'}
                        </p>

                        <h4 className="mt-6 font-semibold">
                            Что уже загружено
                        </h4>
                        <div className="mt-2 divide-y divide-border-subtle border-y border-border-subtle">
                            {syncStatus.resources.map((resource) => (
                                <div
                                    key={resource.key}
                                    className="flex items-center gap-3 py-2.5 text-sm"
                                >
                                    <span className="flex-1">
                                        {resource.label}
                                    </span>
                                    <span
                                        className={cn(
                                            'flex items-center gap-2 text-text-secondary',
                                            resource.status === 'completed' &&
                                                'text-success',
                                            resource.status === 'running' &&
                                                'text-primary',
                                            resource.status === 'skipped' &&
                                                'text-warning',
                                            resource.status === 'failed' &&
                                                'text-destructive',
                                        )}
                                    >
                                        {resource.status === 'completed' ? (
                                            <CheckCircle2 className="size-4" />
                                        ) : resource.status === 'running' ? (
                                            <LoaderCircle className="size-4 animate-spin" />
                                        ) : resource.status === 'skipped' ? (
                                            <CircleAlert className="size-4" />
                                        ) : resource.status === 'failed' ? (
                                            <CircleAlert className="size-4" />
                                        ) : (
                                            <Clock3 className="size-4" />
                                        )}
                                        {resource.status === 'completed'
                                            ? 'Готово'
                                            : resource.status === 'running'
                                              ? 'Загружается'
                                              : resource.status === 'skipped'
                                                ? 'Недоступно'
                                                : resource.status === 'failed'
                                                  ? 'Ошибка'
                                                  : 'Ожидает'}
                                    </span>
                                </div>
                            ))}
                        </div>
                        <Alert
                            className={
                                failed
                                    ? 'mt-5 border-destructive/20 bg-destructive/10'
                                    : partial && !sandbox
                                      ? 'mt-5 border-warning/30 bg-warning/10'
                                      : 'mt-5 border-info/20 bg-info-soft'
                            }
                        >
                            <AlertDescription>
                                {failed
                                    ? (syncStatus.run?.errorSummary ??
                                      'Повторите загрузку или обновите токен кабинета.')
                                    : sandbox
                                      ? syncStatus.completed
                                          ? 'Товары, заказы и продажи из песочницы загружены. Остатки отсутствуют в тестовом контуре WB.'
                                          : 'Загружаем доступные данные песочницы. Остатки в тестовом контуре WB отсутствуют.'
                                      : partial
                                        ? syncStatus.completed
                                            ? 'Товары, заказы и продажи загружены. Остатки недоступны для этого типа токена.'
                                            : 'Остатки недоступны для этого типа токена. Остальные данные продолжают загружаться.'
                                        : 'Можно закрыть эту страницу — загрузка продолжится в фоновом режиме.'}
                            </AlertDescription>
                        </Alert>
                        <div className="mt-5 flex flex-wrap gap-3">
                            {failed && (
                                <Button
                                    type="button"
                                    disabled={retrying}
                                    onClick={() => {
                                        if (retrying) {
                                            return;
                                        }

                                        setRetrying(true);
                                        router.post(
                                            `/settings/cabinets/${syncStatus.account.id}/initial-sync`,
                                            {},
                                            {
                                                onFinish: () =>
                                                    setRetrying(false),
                                            },
                                        );
                                    }}
                                >
                                    <RotateCcw
                                        className={cn(
                                            retrying && 'animate-spin',
                                        )}
                                    />
                                    {retrying
                                        ? 'Запускаем...'
                                        : 'Повторить загрузку'}
                                </Button>
                            )}
                            <Button
                                asChild
                                variant={failed ? 'outline' : 'default'}
                            >
                                <Link
                                    href={
                                        syncStatus.completed
                                            ? '/overview'
                                            : '/settings/cabinets'
                                    }
                                >
                                    {syncStatus.completed
                                        ? 'Перейти к обзору'
                                        : 'Перейти к кабинетам'}
                                </Link>
                            </Button>
                        </div>
                    </div>
                    <aside className="border-t border-border-subtle p-6 lg:border-t-0 lg:border-l lg:p-8">
                        <div className="flex items-center gap-4">
                            <span className="flex size-12 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                <Store />
                            </span>
                            <div>
                                <h3 className="text-lg font-semibold">
                                    {syncStatus.account.name}
                                </h3>
                                <p className="mt-1 flex items-center gap-2 text-sm text-text-secondary">
                                    <span
                                        className={cn(
                                            'size-2 rounded-full',
                                            failed
                                                ? 'bg-destructive'
                                                : partial && !sandbox
                                                  ? 'bg-warning'
                                                  : 'bg-success',
                                        )}
                                    />
                                    {failed
                                        ? 'Требуется повтор загрузки'
                                        : sandbox
                                          ? 'Песочница WB подключена'
                                          : partial
                                            ? 'Подключён частично'
                                            : 'Кабинет подключён'}
                                </p>
                            </div>
                        </div>
                        <p className="mt-6 text-sm text-text-secondary">
                            Показатели появятся на дашборде после завершения
                            первичной загрузки.
                        </p>
                    </aside>
                </div>
            </ConnectionShell>
        </>
    );
}
