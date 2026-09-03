import { Head, Link, useForm } from '@inertiajs/react';
import {
    Boxes,
    ChartNoAxesCombined,
    CheckCircle2,
    CircleAlert,
    PackageSearch,
    ShieldCheck,
    ShoppingCart,
    Store,
} from 'lucide-react';

import { ConnectionShell } from '@/components/settings/connection-shell';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const resources = [
    { key: 'products', label: 'Товары', icon: PackageSearch },
    { key: 'orders', label: 'Заказы', icon: ShoppingCart },
    { key: 'sales', label: 'Продажи', icon: ChartNoAxesCombined },
    { key: 'stocks', label: 'Остатки', icon: Boxes },
];

const requiredResourceKeys = ['products', 'orders', 'sales'];

export default function ConnectionVerification({
    cabinet,
}: {
    cabinet: {
        id: number;
        name: string;
        wbAccountId: string;
        environment: string;
        permissions: string[];
    };
}) {
    const form = useForm({});
    const availableCount = resources.filter((resource) =>
        cabinet.permissions.includes(resource.key),
    ).length;
    const allPermissionsAvailable = availableCount === resources.length;
    const requiredPermissionsAvailable = requiredResourceKeys.every((key) =>
        cabinet.permissions.includes(key),
    );
    const partiallyAvailable =
        requiredPermissionsAvailable && !allPermissionsAvailable;
    const sandbox = cabinet.environment === 'sandbox';

    return (
        <>
            <Head title="Проверка подключения" />
            <ConnectionShell current={2}>
                <div className="grid lg:grid-cols-[1.2fr_.95fr]">
                    <div className="p-6 lg:p-7">
                        <div className="flex items-center gap-3">
                            {allPermissionsAvailable ? (
                                <CheckCircle2 className="size-10 text-success" />
                            ) : (
                                <CircleAlert
                                    className={cn(
                                        'size-10',
                                        partiallyAvailable
                                            ? 'text-warning'
                                            : 'text-destructive',
                                    )}
                                />
                            )}
                            <div>
                                <h3 className="text-2xl font-semibold">
                                    {sandbox
                                        ? 'Тестовый токен проверен'
                                        : allPermissionsAvailable
                                          ? 'Токен проверен'
                                          : partiallyAvailable
                                            ? 'Токен проверен частично'
                                            : 'Недостаточно доступов'}
                                </h3>
                                <p className="mt-1 text-sm text-text-secondary">
                                    {sandbox
                                        ? 'Песочница WB доступна. Остатки в тестовом контуре не поддерживаются.'
                                        : allPermissionsAvailable
                                          ? 'Кабинет найден, необходимые доступы доступны.'
                                          : partiallyAvailable
                                            ? 'Товары и продажи доступны. Остатки недоступны для этого типа токена.'
                                            : 'Создайте новый токен с категориями Content и Statistics.'}
                                </p>
                            </div>
                        </div>
                        <div className="mt-5 flex items-center gap-4 rounded-lg border border-border p-4">
                            <Store className="size-8 text-text-secondary" />
                            <div className="flex-1">
                                <p className="font-semibold">{cabinet.name}</p>
                                <p className="text-sm text-text-secondary">
                                    WB ID {cabinet.wbAccountId}
                                </p>
                            </div>
                            <span
                                className={cn(
                                    'flex items-center gap-2 text-xs',
                                    allPermissionsAvailable
                                        ? 'text-text-secondary'
                                        : requiredPermissionsAvailable
                                          ? 'text-warning'
                                          : 'text-destructive',
                                )}
                            >
                                <span
                                    className={cn(
                                        'size-2 rounded-full',
                                        allPermissionsAvailable
                                            ? 'bg-success'
                                            : requiredPermissionsAvailable
                                              ? 'bg-warning'
                                              : 'bg-destructive',
                                    )}
                                />
                                {sandbox
                                    ? 'Готов к подключению песочницы'
                                    : allPermissionsAvailable
                                      ? 'Готов к подключению'
                                      : requiredPermissionsAvailable
                                        ? 'Готов к частичному подключению'
                                        : 'Недоступны обязательные разделы'}
                            </span>
                        </div>
                        <h4 className="mt-5 font-semibold">
                            Проверенные доступы
                        </h4>
                        <div className="mt-3 overflow-hidden rounded-lg border border-border">
                            {resources.map((resource) => {
                                const Icon = resource.icon;
                                const available = cabinet.permissions.includes(
                                    resource.key,
                                );

                                return (
                                    <div
                                        key={resource.key}
                                        className="flex items-center gap-3 border-b border-border-subtle px-4 py-2.5 last:border-b-0"
                                    >
                                        <Icon className="size-4 text-text-secondary" />
                                        <span className="flex-1 text-sm">
                                            {resource.label}
                                        </span>
                                        <span
                                            className={cn(
                                                'flex items-center gap-2 text-sm text-text-secondary',
                                                !available && 'text-warning',
                                            )}
                                        >
                                            {available ? (
                                                <CheckCircle2 className="size-4 text-success" />
                                            ) : (
                                                <CircleAlert className="size-4" />
                                            )}
                                            {available
                                                ? 'Доступ есть'
                                                : sandbox &&
                                                    resource.key === 'stocks'
                                                  ? 'Нет в песочнице'
                                                  : 'Нет доступа'}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                        <form
                            className="mt-5 flex flex-wrap gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(
                                    `/settings/cabinets/${cabinet.id}/initial-sync`,
                                );
                            }}
                        >
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    !requiredPermissionsAvailable
                                }
                            >
                                {form.processing
                                    ? 'Подключаем…'
                                    : 'Подключить кабинет'}
                            </Button>
                            <Button asChild variant="outline">
                                <Link href="/settings/cabinets/connect">
                                    Ввести другой токен
                                </Link>
                            </Button>
                        </form>
                    </div>
                    <aside className="border-t border-border-subtle p-6 lg:border-t-0 lg:border-l lg:p-8">
                        <div className="flex items-center gap-3">
                            <ShieldCheck className="size-9 text-text-secondary" />
                            <h3 className="text-xl font-semibold">
                                {sandbox
                                    ? 'Песочница WB'
                                    : 'Подключение готово'}
                            </h3>
                        </div>
                        <ul className="mt-7 space-y-5 text-sm text-text-secondary">
                            <li className="flex gap-3">
                                <CheckCircle2 className="size-5 text-success" />
                                Токен действителен
                            </li>
                            <li className="flex gap-3">
                                {sandbox ? (
                                    <CircleAlert className="size-5 text-warning" />
                                ) : (
                                    <CheckCircle2 className="size-5 text-success" />
                                )}
                                {sandbox
                                    ? 'Тестовый токен работает на чтение и запись'
                                    : 'Доступ только на чтение'}
                            </li>
                            <li className="flex gap-3">
                                <CheckCircle2 className="size-5 text-success" />
                                {availableCount} из {resources.length} разделов
                                доступны
                            </li>
                        </ul>
                    </aside>
                </div>
            </ConnectionShell>
        </>
    );
}
