import { Head, useForm } from '@inertiajs/react';
import { CircleCheck, LoaderCircle, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';

import { AuthHeading } from '@/components/auth/auth-heading';
import { PasswordInput } from '@/components/auth/password-input';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { AuthLayout } from '@/layouts/auth-layout';
import { cn } from '@/lib/utils';

type ResetPasswordProps = {
    email: string;
    token: string;
};

export default function ResetPassword({ email, token }: ResetPasswordProps) {
    const form = useForm({
        email,
        token,
        password: '',
        password_confirmation: '',
    });
    const hasMinimumLength = form.data.password.length >= 8;
    const hasNumber = /\d/.test(form.data.password);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/reset-password/${token}`, {
            preserveScroll: true,
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <>
            <Head title="Новый пароль" />
            <AuthLayout>
                <AuthHeading
                    title="Создать новый пароль"
                    description={`Задайте новый пароль для ${email || 'вашего аккаунта'}.`}
                    backToLogin
                />

                <form className="mt-9" onSubmit={submit}>
                    <FieldGroup className="gap-6">
                        <Field data-invalid={Boolean(form.errors.password)}>
                            <FieldLabel htmlFor="password">
                                Новый пароль
                            </FieldLabel>
                            <PasswordInput
                                id="password"
                                autoComplete="new-password"
                                passwordrules="minlength: 8; required: digit; required: letter;"
                                required
                                value={form.data.password}
                                onChange={(event) =>
                                    form.setData('password', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.password)}
                                autoFocus
                            />
                            <div className="mt-1 flex flex-col gap-3 text-sm text-text-secondary">
                                <p
                                    className={cn(
                                        'flex items-center gap-3',
                                        hasMinimumLength && 'text-success',
                                    )}
                                >
                                    <CircleCheck />
                                    Не менее 8 символов
                                </p>
                                <p
                                    className={cn(
                                        'flex items-center gap-3',
                                        hasNumber && 'text-success',
                                    )}
                                >
                                    <CircleCheck />
                                    Минимум одна цифра
                                </p>
                            </div>
                            <FieldError>{form.errors.password}</FieldError>
                        </Field>

                        <Field
                            data-invalid={Boolean(
                                form.errors.password_confirmation,
                            )}
                        >
                            <FieldLabel htmlFor="password_confirmation">
                                Повторите новый пароль
                            </FieldLabel>
                            <PasswordInput
                                id="password_confirmation"
                                autoComplete="new-password"
                                required
                                value={form.data.password_confirmation}
                                onChange={(event) =>
                                    form.setData(
                                        'password_confirmation',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    form.errors.password_confirmation,
                                )}
                            />
                            <FieldError>
                                {form.errors.password_confirmation}
                            </FieldError>
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
                            Сохранить новый пароль
                        </Button>

                        <p className="flex items-start gap-3 text-sm leading-5 text-text-secondary">
                            <ShieldCheck className="mt-0.5 shrink-0 text-text-secondary" />
                            После сохранения остальные активные сессии будут
                            завершены.
                        </p>
                    </FieldGroup>
                </form>
            </AuthLayout>
        </>
    );
}
