import { Head, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    Mail,
    RefreshCw,
    RotateCcw,
    TrendingDown,
    TriangleAlert,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ToggleSwitch } from '@/components/ui/toggle-switch';
import { SettingsLayout } from '@/layouts/settings-layout';

type EventSetting = {
    event: string;
    label: string;
    description: string;
    threshold: number | null;
    unit: string | null;
    enabled: boolean;
    frequency: string | null;
};

const icons = {
    low_stock: TriangleAlert,
    sales_decline: TrendingDown,
    returns_growth: RotateCcw,
    sync_failed: RefreshCw,
    daily_digest: CalendarDays,
};

const options: Record<
    string,
    Array<{ value: number | string; label: string }>
> = {
    low_stock: [
        { value: 3, label: 'меньше 3 дней' },
        { value: 7, label: 'меньше 7 дней' },
        { value: 14, label: 'меньше 14 дней' },
    ],
    sales_decline: [
        { value: 10, label: 'снижение от 10%' },
        { value: 20, label: 'снижение от 20%' },
        { value: 30, label: 'снижение от 30%' },
    ],
    returns_growth: [
        { value: 2, label: 'рост от 2 п.п.' },
        { value: 3, label: 'рост от 3 п.п.' },
        { value: 5, label: 'рост от 5 п.п.' },
    ],
    daily_digest: [
        { value: 'daily', label: 'Ежедневно' },
        { value: 'weekly', label: 'Еженедельно' },
    ],
};

export default function Notifications({
    settings,
}: {
    settings: {
        cabinet: { id: number; name: string };
        email: string;
        emailVerified: boolean;
        emailEnabled: boolean;
        events: EventSetting[];
    };
}) {
    const form = useForm({
        seller_account_id: settings.cabinet.id,
        email_enabled: settings.emailEnabled,
        events: Object.fromEntries(
            settings.events.map((event) => [
                event.event,
                {
                    enabled: event.enabled,
                    threshold: event.threshold,
                    frequency: event.frequency,
                },
            ]),
        ) as Record<
            string,
            {
                enabled: boolean;
                threshold: number | null;
                frequency: string | null;
            }
        >,
    });

    function updateEvent(
        event: string,
        values: Partial<(typeof form.data.events)[string]>,
    ) {
        form.setData('events', {
            ...form.data.events,
            [event]: { ...form.data.events[event], ...values },
        });
    }

    return (
        <>
            <Head title="Уведомления" />
            <SettingsLayout activeTab="notifications">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 className="text-lg font-semibold">Уведомления</h2>
                        <p className="mt-1 text-sm text-text-secondary">
                            Получайте только важные сигналы по выбранному
                            кабинету.
                        </p>
                    </div>
                    <p className="text-sm text-text-secondary">
                        Настройки для кабинета{' '}
                        <strong className="text-foreground">
                            {settings.cabinet.name}
                        </strong>
                    </p>
                </div>

                <section className="mt-5 rounded-lg border border-border p-5">
                    <h3 className="font-semibold">Канал доставки</h3>
                    <div className="mt-4 flex items-center gap-4">
                        <span className="flex size-12 items-center justify-center rounded-lg border bg-surface-subtle text-text-secondary">
                            <Mail />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="font-semibold">Электронная почта</p>
                            <p className="truncate text-sm text-text-secondary">
                                {settings.email}
                            </p>
                        </div>
                        {settings.emailVerified && (
                            <Badge variant="success">Подтверждена</Badge>
                        )}
                        <ToggleSwitch
                            checked={form.data.email_enabled}
                            label="Уведомления по электронной почте"
                            onCheckedChange={(checked) =>
                                form.setData('email_enabled', checked)
                            }
                        />
                    </div>
                    <p className="mt-4 text-sm text-text-secondary">
                        Уведомления отправляются на адрес из профиля.
                    </p>
                </section>

                <form
                    className="mt-5 rounded-lg border border-border p-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch('/settings/notifications', {
                            preserveScroll: true,
                        });
                    }}
                >
                    <h3 className="font-semibold">События</h3>
                    <p className="mt-1 text-sm text-text-secondary">
                        Выберите, о чём сообщать для кабинета «
                        {settings.cabinet.name}».
                    </p>
                    <div className="mt-4 divide-y divide-border-subtle">
                        {settings.events.map((event) => {
                            const Icon =
                                icons[event.event as keyof typeof icons] ??
                                TriangleAlert;
                            const current = form.data.events[event.event];

                            return (
                                <div
                                    key={event.event}
                                    className="grid gap-4 py-4 first:pt-0 last:pb-0 sm:grid-cols-[1fr_260px_44px] sm:items-center"
                                >
                                    <div className="flex gap-4">
                                        <span className="flex size-11 shrink-0 items-center justify-center rounded-lg border bg-surface-subtle text-info">
                                            <Icon className="size-5" />
                                        </span>
                                        <div>
                                            <p className="font-semibold">
                                                {event.label}
                                            </p>
                                            <p className="mt-0.5 text-sm text-text-secondary">
                                                {event.description}
                                            </p>
                                        </div>
                                    </div>
                                    {options[event.event] ? (
                                        <select
                                            aria-label={`Условие: ${event.label}`}
                                            className="h-[42px] rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/15"
                                            value={
                                                event.event === 'daily_digest'
                                                    ? (current.frequency ??
                                                      'daily')
                                                    : (current.threshold ?? '')
                                            }
                                            onChange={(change) =>
                                                event.event === 'daily_digest'
                                                    ? updateEvent(event.event, {
                                                          frequency:
                                                              change.target
                                                                  .value,
                                                      })
                                                    : updateEvent(event.event, {
                                                          threshold: Number(
                                                              change.target
                                                                  .value,
                                                          ),
                                                      })
                                            }
                                        >
                                            {options[event.event].map(
                                                (option) => (
                                                    <option
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    ) : (
                                        <span />
                                    )}
                                    <ToggleSwitch
                                        checked={current.enabled}
                                        disabled={!form.data.email_enabled}
                                        label={event.label}
                                        onCheckedChange={(checked) =>
                                            updateEvent(event.event, {
                                                enabled: checked,
                                            })
                                        }
                                    />
                                </div>
                            );
                        })}
                    </div>
                    <p className="mt-5 text-sm text-text-secondary">
                        Изменения применяются только к кабинету «
                        {settings.cabinet.name}».
                    </p>
                    <Button className="mt-4" disabled={form.processing}>
                        {form.recentlySuccessful
                            ? 'Сохранено'
                            : 'Сохранить настройки'}
                    </Button>
                </form>
            </SettingsLayout>
        </>
    );
}
