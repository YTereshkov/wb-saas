import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';

import { AuthHeading } from '@/components/auth/auth-heading';
import { AuthStatus } from '@/components/auth/auth-status';
import { PasswordInput } from '@/components/auth/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldSeparator,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { AuthLayout } from '@/layouts/auth-layout';

type LoginProps = {
    status?: string;
    magicLinkSent: boolean;
    magicLinkError?: string;
};

export default function Login({
    status,
    magicLinkSent,
    magicLinkError,
}: LoginProps) {
    const login = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const magicLink = useForm({ email: '' });

    function updateEmail(email: string) {
        login.setData('email', email);
        magicLink.setData('email', email);
    }

    function submitLogin(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        login.post('/login', {
            preserveScroll: true,
            onFinish: () => login.reset('password'),
        });
    }

    function submitMagicLink() {
        login.clearErrors();
        magicLink.post('/login/magic-link', {
            preserveScroll: true,
        });
    }

    const emailError = login.errors.email ?? magicLink.errors.email;

    return (
        <>
            <Head title="Вход" />
            <AuthLayout>
                <AuthHeading
                    title="Войти в SellerScope"
                    description="Продолжите работу с аналитикой магазина."
                />

                {status ? (
                    <div className="mt-6">
                        <AuthStatus
                            variant={magicLinkSent ? 'success' : 'info'}
                        >
                            {status}
                        </AuthStatus>
                    </div>
                ) : null}

                {magicLinkError ? (
                    <div className="mt-6">
                        <AuthStatus variant="destructive">
                            {magicLinkError}
                        </AuthStatus>
                    </div>
                ) : null}

                <form className="mt-8" onSubmit={submitLogin}>
                    <FieldGroup className="gap-5">
                        <Field data-invalid={Boolean(emailError)}>
                            <FieldLabel htmlFor="email">
                                Электронная почта
                            </FieldLabel>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="email"
                                required
                                value={login.data.email}
                                onChange={(event) =>
                                    updateEmail(event.target.value)
                                }
                                aria-invalid={Boolean(emailError)}
                                autoFocus
                            />
                            <FieldError>{emailError}</FieldError>
                        </Field>

                        <Field data-invalid={Boolean(login.errors.password)}>
                            <div className="flex items-center justify-between gap-4">
                                <FieldLabel htmlFor="password">
                                    Пароль
                                </FieldLabel>
                                <Link
                                    href="/forgot-password"
                                    className="rounded-sm text-xs font-medium text-primary outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/20"
                                >
                                    Забыли пароль?
                                </Link>
                            </div>
                            <PasswordInput
                                id="password"
                                autoComplete="current-password"
                                required
                                value={login.data.password}
                                onChange={(event) =>
                                    login.setData(
                                        'password',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(login.errors.password)}
                            />
                            <FieldError>{login.errors.password}</FieldError>
                        </Field>

                        <Field orientation="horizontal" className="gap-3">
                            <Checkbox
                                id="remember"
                                checked={login.data.remember}
                                onCheckedChange={(checked) =>
                                    login.setData('remember', checked === true)
                                }
                            />
                            <FieldLabel
                                htmlFor="remember"
                                className="font-normal text-text-secondary"
                            >
                                Запомнить меня
                            </FieldLabel>
                        </Field>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={login.processing}
                        >
                            {login.processing ? (
                                <LoaderCircle
                                    data-icon="inline-start"
                                    className="animate-spin"
                                />
                            ) : null}
                            Войти
                        </Button>

                        <FieldSeparator>или</FieldSeparator>

                        <Button
                            type="button"
                            variant="outline"
                            className="w-full"
                            onClick={submitMagicLink}
                            disabled={magicLink.processing}
                        >
                            {magicLink.processing ? (
                                <LoaderCircle
                                    data-icon="inline-start"
                                    className="animate-spin"
                                />
                            ) : null}
                            Войти по одноразовой ссылке
                        </Button>

                        <p className="-mt-2 text-xs text-muted-foreground">
                            Отправим безопасную ссылку на электронную почту.
                        </p>
                    </FieldGroup>
                </form>

                <p className="mt-7 text-sm text-muted-foreground">
                    Нет аккаунта?{' '}
                    <Link
                        href="/register"
                        className="font-semibold text-primary hover:underline"
                    >
                        Создать аккаунт
                    </Link>
                </p>
                <p className="mt-20 text-xs leading-5 text-muted-foreground">
                    Продолжая, вы принимаете{' '}
                    <span className="text-primary">условия использования</span>{' '}
                    и{' '}
                    <span className="text-primary">
                        политику конфиденциальности
                    </span>
                    .
                </p>
            </AuthLayout>
        </>
    );
}
