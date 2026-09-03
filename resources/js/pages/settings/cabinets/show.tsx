import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    CircleAlert,
    RefreshCw,
    ShieldCheck,
    Store,
    Trash2,
} from 'lucide-react';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { SettingsLayout } from '@/layouts/settings-layout';

type Cabinet = {
    id: number;
    name: string;
    wbAccountId: string | null;
    environment: string;
    status: string;
    connected: boolean;
    lastSyncLabel: string;
    credential: {
        valid: boolean;
        permissions: string[];
        verifiedAt: string | null;
        verifiedLabel: string | null;
    };
    syncRuns: Array<{
        id: number;
        type: string;
        status: string;
        progress: number;
        startedAt: string | null;
        startedLabel: string;
        finishedLabel: string | null;
        errorSummary: string | null;
    }>;
};

export default function CabinetShow({ cabinet }: { cabinet: Cabinet }) {
    const nameForm = useForm({ name: cabinet.name });
    const tokenForm = useForm({ token: '' });
    const syncForm = useForm({});
    const deleteForm = useForm({});

    return (
        <>
            <Head title={cabinet.name} />
            <SettingsLayout activeTab="cabinets">
                <Button asChild variant="link" className="mb-5 h-auto px-0">
                    <Link href="/settings/cabinets">
                        <ArrowLeft /> К подключённым кабинетам
                    </Link>
                </Button>

                <section className="flex flex-col gap-4 rounded-lg border border-border bg-surface-subtle p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex min-w-0 items-center gap-4">
                        <span className="flex size-12 shrink-0 items-center justify-center rounded-lg border bg-background text-text-secondary">
                            <Store />
                        </span>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="truncate text-lg font-semibold">
                                    {cabinet.name}
                                </h2>
                                <Badge
                                    variant={
                                        cabinet.connected
                                            ? 'success'
                                            : 'destructive'
                                    }
                                >
                                    {cabinet.connected ? (
                                        <CheckCircle2 />
                                    ) : (
                                        <CircleAlert />
                                    )}
                                    {cabinet.connected
                                        ? 'Подключён'
                                        : 'Отключён'}
                                </Badge>
                                {cabinet.environment === 'sandbox' && (
                                    <Badge variant="secondary">
                                        Песочница WB
                                    </Badge>
                                )}
                            </div>
                            <p className="mt-1 text-sm text-text-secondary">
                                WB ID {cabinet.wbAccountId ?? 'не указан'} ·{' '}
                                {cabinet.lastSyncLabel}
                            </p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={!cabinet.connected || syncForm.processing}
                        onClick={() =>
                            syncForm.post(
                                `/settings/cabinets/${cabinet.id}/sync`,
                            )
                        }
                    >
                        <RefreshCw
                            className={
                                syncForm.processing ? 'animate-spin' : ''
                            }
                        />
                        Обновить данные
                    </Button>
                </section>

                <div className="mt-5 grid gap-5 lg:grid-cols-2">
                    <form
                        className="rounded-lg border border-border p-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            nameForm.patch(`/settings/cabinets/${cabinet.id}`, {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <h3 className="font-semibold">Основные настройки</h3>
                        <p className="mt-1 text-sm text-text-secondary">
                            Название используется только внутри SellerScope.
                        </p>
                        <Field
                            className="mt-5"
                            data-invalid={Boolean(nameForm.errors.name)}
                        >
                            <FieldLabel htmlFor="cabinet-name">
                                Название кабинета
                            </FieldLabel>
                            <Input
                                id="cabinet-name"
                                value={nameForm.data.name}
                                onChange={(event) =>
                                    nameForm.setData('name', event.target.value)
                                }
                            />
                            <FieldError>{nameForm.errors.name}</FieldError>
                        </Field>
                        <Field className="mt-4">
                            <FieldLabel htmlFor="wb-id">WB ID</FieldLabel>
                            <Input
                                id="wb-id"
                                value={cabinet.wbAccountId ?? ''}
                                disabled
                            />
                        </Field>
                        <Button className="mt-5" disabled={nameForm.processing}>
                            Сохранить
                        </Button>
                    </form>

                    <form
                        className="rounded-lg border border-border p-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            tokenForm.put(
                                `/settings/cabinets/${cabinet.id}/credentials`,
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <h3 className="font-semibold">Статус подключения</h3>
                        <div
                            className={
                                cabinet.credential.valid
                                    ? 'mt-4 flex items-center gap-3 rounded-md bg-success-soft p-3 text-sm text-success'
                                    : 'mt-4 flex items-center gap-3 rounded-md bg-destructive/10 p-3 text-sm text-destructive'
                            }
                        >
                            {cabinet.credential.valid ? (
                                <ShieldCheck className="size-5" />
                            ) : (
                                <CircleAlert className="size-5" />
                            )}
                            <span>
                                <strong>
                                    {cabinet.credential.valid
                                        ? 'Токен действителен'
                                        : 'Токен недействителен'}
                                </strong>
                                <br />
                                {cabinet.credential.valid
                                    ? `Проверен ${cabinet.credential.verifiedLabel ?? 'недавно'}`
                                    : 'Введите новый токен, чтобы продолжить синхронизацию'}
                            </span>
                        </div>
                        <p className="mt-4 text-sm font-medium">
                            Доступные разделы
                        </p>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {cabinet.credential.permissions.map(
                                (permission) => (
                                    <Badge key={permission} variant="secondary">
                                        {permission}
                                    </Badge>
                                ),
                            )}
                        </div>
                        <Field
                            className="mt-4"
                            data-invalid={Boolean(tokenForm.errors.token)}
                        >
                            <FieldLabel htmlFor="new-token">
                                Новый API-токен
                            </FieldLabel>
                            <Input
                                id="new-token"
                                type="password"
                                autoComplete="off"
                                value={tokenForm.data.token}
                                onChange={(event) =>
                                    tokenForm.setData(
                                        'token',
                                        event.target.value,
                                    )
                                }
                            />
                            <FieldError>{tokenForm.errors.token}</FieldError>
                        </Field>
                        <Button
                            className="mt-5"
                            variant="outline"
                            disabled={tokenForm.processing}
                        >
                            Обновить токен
                        </Button>
                    </form>
                </div>

                <section className="mt-5 overflow-hidden rounded-lg border border-border">
                    <div className="px-5 py-4">
                        <h3 className="font-semibold">История синхронизации</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[680px] text-left text-sm">
                            <thead className="border-y border-border-subtle bg-surface-subtle text-xs text-text-secondary">
                                <tr>
                                    <th className="px-5 py-3 font-medium">
                                        Запуск
                                    </th>
                                    <th className="px-5 py-3 font-medium">
                                        Тип
                                    </th>
                                    <th className="px-5 py-3 font-medium">
                                        Статус
                                    </th>
                                    <th className="px-5 py-3 font-medium">
                                        Завершён
                                    </th>
                                    <th className="px-5 py-3 font-medium">
                                        Результат
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {cabinet.syncRuns.map((run) => (
                                    <tr
                                        key={run.id}
                                        className="border-b border-border-subtle last:border-0"
                                    >
                                        <td className="px-5 py-3">
                                            {run.startedLabel}
                                        </td>
                                        <td className="px-5 py-3">
                                            {run.type === 'initial'
                                                ? 'Первичная'
                                                : 'Обновление'}
                                        </td>
                                        <td className="px-5 py-3">
                                            <Badge
                                                variant={
                                                    run.status === 'completed'
                                                        ? 'success'
                                                        : run.status ===
                                                            'failed'
                                                          ? 'destructive'
                                                          : 'info'
                                                }
                                            >
                                                {run.status === 'completed'
                                                    ? 'Успешно'
                                                    : run.status === 'failed'
                                                      ? 'Ошибка'
                                                      : `${run.progress}%`}
                                            </Badge>
                                        </td>
                                        <td className="px-5 py-3">
                                            {run.finishedLabel ?? '—'}
                                        </td>
                                        <td className="px-5 py-3 text-text-secondary">
                                            {run.errorSummary ??
                                                'Данные обновлены'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="mt-5 rounded-lg border border-destructive/30 p-5">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="font-semibold text-destructive">
                                Удалить кабинет
                            </h3>
                            <p className="mt-1 text-sm text-text-secondary">
                                Кабинет и все связанные данные будут удалены без
                                возможности восстановления.
                            </p>
                        </div>
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button variant="destructive">
                                    <Trash2 />
                                    Удалить
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>
                                        Удалить «{cabinet.name}»?
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        Кабинет, токен и все загруженные данные
                                        будут удалены без возможности
                                        восстановления.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel
                                        disabled={deleteForm.processing}
                                    >
                                        Отмена
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        className={buttonVariants({
                                            variant: 'destructive',
                                        })}
                                        disabled={deleteForm.processing}
                                        onClick={(event) => {
                                            event.preventDefault();
                                            deleteForm.delete(
                                                `/settings/cabinets/${cabinet.id}`,
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
                    </div>
                </section>
            </SettingsLayout>
        </>
    );
}
