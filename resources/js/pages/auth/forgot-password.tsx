import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';

import { AuthHeading } from '@/components/auth/auth-heading';
import { AuthStatus } from '@/components/auth/auth-status';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { AuthLayout } from '@/layouts/auth-layout';

type ForgotPasswordProps = {
    status?: string;
};

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    const form = useForm({ email: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/forgot-password', { preserveScroll: true });
    }

    return (
        <>
            <Head title="Восстановление пароля" />
            <AuthLayout>
                <AuthHeading
                    title="Восстановить пароль"
                    description="Введите электронную почту — отправим ссылку для создания нового пароля."
                    backToLogin
                />

                {status ? (
                    <div className="mt-7">
                        <AuthStatus variant="success">{status}</AuthStatus>
                    </div>
                ) : null}

                <form className="mt-12" onSubmit={submit}>
                    <FieldGroup className="gap-7">
                        <Field data-invalid={Boolean(form.errors.email)}>
                            <FieldLabel htmlFor="email">
                                Электронная почта
                            </FieldLabel>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="email"
                                required
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.email)}
                                autoFocus
                            />
                            <FieldError>{form.errors.email}</FieldError>
                        </Field>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={form.processing}
                        >
                            {form.processing ? (
                                <LoaderCircle
                                    data-icon="inline-start"
                                    className="animate-spin"
                                />
                            ) : null}
                            Отправить ссылку
                        </Button>

                        <AuthStatus>
                            Ссылка будет действовать 30 минут.
                        </AuthStatus>
                    </FieldGroup>
                </form>

                <p className="mt-10 text-sm text-muted-foreground">
                    Вспомнили пароль?{' '}
                    <Link
                        href="/login"
                        className="font-semibold text-primary hover:underline"
                    >
                        Войти
                    </Link>
                </p>
            </AuthLayout>
        </>
    );
}
