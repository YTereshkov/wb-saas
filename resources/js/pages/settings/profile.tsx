import { Head, useForm } from '@inertiajs/react';
import { Laptop, LogOut, ShieldCheck } from 'lucide-react';

import { PasswordInput } from '@/components/auth/password-input';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { SettingsLayout } from '@/layouts/settings-layout';

type ProfileData = {
    firstName: string;
    lastName: string;
    email: string;
    emailVerified: boolean;
    passwordChangedLabel: string | null;
};

export default function Profile({
    profile,
    sessions,
}: {
    profile: ProfileData;
    sessions: Array<{
        id: string;
        current: boolean;
        ipAddress: string | null;
        device: string;
        lastActiveAt: string;
        lastActiveLabel: string;
    }>;
}) {
    const profileForm = useForm({
        first_name: profile.firstName,
        last_name: profile.lastName,
        email: profile.email,
    });
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const sessionsForm = useForm({});
    const initials =
        `${profile.firstName[0] ?? ''}${profile.lastName[0] ?? ''}`.toUpperCase();

    return (
        <>
            <Head title="Профиль" />
            <SettingsLayout activeTab="profile">
                <section className="rounded-lg border border-border p-5 sm:p-6">
                    <h2 className="text-lg font-semibold">Личные данные</h2>
                    <p className="mt-1 text-sm text-text-secondary">
                        Информация, которая используется в вашем аккаунте
                    </p>
                    <div className="mt-6 grid gap-6 lg:grid-cols-[220px_1fr]">
                        <div className="flex items-center gap-3 lg:items-start">
                            <Avatar className="size-12">
                                <AvatarFallback>
                                    {initials || 'SS'}
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <p className="font-semibold">
                                    {profile.firstName} {profile.lastName}
                                </p>
                                <p className="text-sm text-text-secondary">
                                    Владелец аккаунта
                                </p>
                            </div>
                        </div>
                        <form
                            className="grid gap-4 sm:grid-cols-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                profileForm.patch('/settings/profile', {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <Field
                                data-invalid={Boolean(
                                    profileForm.errors.first_name,
                                )}
                            >
                                <FieldLabel htmlFor="first-name">
                                    Имя
                                </FieldLabel>
                                <Input
                                    id="first-name"
                                    autoComplete="given-name"
                                    value={profileForm.data.first_name}
                                    onChange={(event) =>
                                        profileForm.setData(
                                            'first_name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {profileForm.errors.first_name}
                                </FieldError>
                            </Field>
                            <Field
                                data-invalid={Boolean(
                                    profileForm.errors.last_name,
                                )}
                            >
                                <FieldLabel htmlFor="last-name">
                                    Фамилия
                                </FieldLabel>
                                <Input
                                    id="last-name"
                                    autoComplete="family-name"
                                    value={profileForm.data.last_name}
                                    onChange={(event) =>
                                        profileForm.setData(
                                            'last_name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {profileForm.errors.last_name}
                                </FieldError>
                            </Field>
                            <Field
                                className="sm:col-span-2"
                                data-invalid={Boolean(profileForm.errors.email)}
                            >
                                <FieldLabel htmlFor="profile-email">
                                    Электронная почта
                                </FieldLabel>
                                <Input
                                    id="profile-email"
                                    type="email"
                                    autoComplete="email"
                                    value={profileForm.data.email}
                                    onChange={(event) =>
                                        profileForm.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldDescription>
                                    Используется для входа и важных уведомлений
                                </FieldDescription>
                                <FieldError>
                                    {profileForm.errors.email}
                                </FieldError>
                            </Field>
                            <div className="sm:col-span-2">
                                <Button disabled={profileForm.processing}>
                                    {profileForm.recentlySuccessful
                                        ? 'Сохранено'
                                        : 'Сохранить изменения'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </section>

                <section className="mt-5 rounded-lg border border-border p-5 sm:p-6">
                    <div className="grid gap-7 lg:grid-cols-[1fr_360px]">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                passwordForm.put('/settings/profile/password', {
                                    preserveScroll: true,
                                    onSuccess: () => passwordForm.reset(),
                                });
                            }}
                        >
                            <h2 className="text-lg font-semibold">
                                Безопасность
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                Измените пароль для входа в SellerScope
                            </p>
                            <div className="mt-5 grid gap-4">
                                <Field
                                    data-invalid={Boolean(
                                        passwordForm.errors.current_password,
                                    )}
                                >
                                    <FieldLabel htmlFor="current-password">
                                        Текущий пароль
                                    </FieldLabel>
                                    <PasswordInput
                                        id="current-password"
                                        autoComplete="current-password"
                                        value={
                                            passwordForm.data.current_password
                                        }
                                        onChange={(event) =>
                                            passwordForm.setData(
                                                'current_password',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError>
                                        {passwordForm.errors.current_password}
                                    </FieldError>
                                </Field>
                                <Field
                                    data-invalid={Boolean(
                                        passwordForm.errors.password,
                                    )}
                                >
                                    <FieldLabel htmlFor="new-password">
                                        Новый пароль
                                    </FieldLabel>
                                    <PasswordInput
                                        id="new-password"
                                        autoComplete="new-password"
                                        value={passwordForm.data.password}
                                        onChange={(event) =>
                                            passwordForm.setData(
                                                'password',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError>
                                        {passwordForm.errors.password}
                                    </FieldError>
                                </Field>
                                <Field
                                    data-invalid={Boolean(
                                        passwordForm.errors
                                            .password_confirmation,
                                    )}
                                >
                                    <FieldLabel htmlFor="confirm-password">
                                        Повторите новый пароль
                                    </FieldLabel>
                                    <PasswordInput
                                        id="confirm-password"
                                        autoComplete="new-password"
                                        value={
                                            passwordForm.data
                                                .password_confirmation
                                        }
                                        aria-invalid={Boolean(
                                            passwordForm.errors
                                                .password_confirmation,
                                        )}
                                        onChange={(event) =>
                                            passwordForm.setData(
                                                'password_confirmation',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldDescription>
                                        Не менее 8 символов, включая цифру
                                    </FieldDescription>
                                    <FieldError>
                                        {
                                            passwordForm.errors
                                                .password_confirmation
                                        }
                                    </FieldError>
                                </Field>
                            </div>
                            <Button
                                className="mt-5"
                                disabled={passwordForm.processing}
                            >
                                Обновить пароль
                            </Button>
                        </form>
                        <aside className="border-t border-border-subtle pt-6 lg:border-t-0 lg:border-l lg:pt-12 lg:pl-10">
                            <ShieldCheck className="size-10 text-text-secondary" />
                            <h3 className="mt-4 font-semibold">
                                Защита аккаунта
                            </h3>
                            <p className="mt-3 text-sm text-text-secondary">
                                Пароль изменён{' '}
                                {profile.passwordChangedLabel ?? 'недавно'}.
                            </p>
                            <p className="mt-2 text-sm text-text-secondary">
                                После смены пароля остальные сеансы будут
                                завершены.
                            </p>
                        </aside>
                    </div>
                </section>

                <section className="mt-5 rounded-lg border border-border p-5 sm:p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-lg font-semibold">
                                Активные сеансы
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                Устройства, на которых выполнен вход
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            disabled={
                                sessionsForm.processing ||
                                sessions.filter((session) => !session.current)
                                    .length === 0
                            }
                            onClick={() =>
                                sessionsForm.delete(
                                    '/settings/profile/sessions',
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <LogOut />
                            Завершить остальные
                        </Button>
                    </div>
                    <div className="mt-4 divide-y divide-border-subtle">
                        {sessions.map((session) => (
                            <div
                                key={session.id}
                                className="flex items-center gap-4 py-4"
                            >
                                <span className="flex size-10 items-center justify-center rounded-lg bg-surface-subtle text-text-secondary">
                                    <Laptop />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">
                                        {session.device}
                                    </p>
                                    <p className="text-sm text-text-secondary">
                                        {session.ipAddress ?? 'IP неизвестен'} ·{' '}
                                        {session.lastActiveLabel}
                                    </p>
                                </div>
                                {session.current && (
                                    <Badge variant="success">Текущий</Badge>
                                )}
                            </div>
                        ))}
                    </div>
                </section>
            </SettingsLayout>
        </>
    );
}
