import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';

import { AuthHeading } from '@/components/auth/auth-heading';
import { PasswordInput } from '@/components/auth/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { AuthLayout } from '@/layouts/auth-layout';

export default function Register() {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        terms: false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/register', {
            preserveScroll: true,
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <>
            <Head title="Регистрация" />
            <AuthLayout>
                <AuthHeading
                    title="Создать аккаунт"
                    description="Начните работу с аналитикой магазина."
                />

                <form className="mt-7" onSubmit={submit}>
                    <FieldGroup className="gap-4">
                        <Field data-invalid={Boolean(form.errors.name)}>
                            <FieldLabel htmlFor="name">Имя</FieldLabel>
                            <Input
                                id="name"
                                autoComplete="name"
                                required
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.name)}
                                autoFocus
                            />
                            <FieldError>{form.errors.name}</FieldError>
                        </Field>

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
                            />
                            <FieldError>{form.errors.email}</FieldError>
                        </Field>

                        <Field data-invalid={Boolean(form.errors.password)}>
                            <FieldLabel htmlFor="password">Пароль</FieldLabel>
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
                            />
                            <FieldDescription>
                                Не менее 8 символов, включая цифру
                            </FieldDescription>
                            <FieldError>{form.errors.password}</FieldError>
                        </Field>

                        <Field
                            data-invalid={Boolean(
                                form.errors.password_confirmation,
                            )}
                        >
                            <FieldLabel htmlFor="password_confirmation">
                                Повторите пароль
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

                        <Field
                            orientation="horizontal"
                            data-invalid={Boolean(form.errors.terms)}
                            className="items-start gap-3"
                        >
                            <Checkbox
                                id="terms"
                                required
                                checked={form.data.terms}
                                onCheckedChange={(checked) =>
                                    form.setData('terms', checked === true)
                                }
                                aria-invalid={Boolean(form.errors.terms)}
                            />
                            <div className="flex flex-col gap-1">
                                <FieldLabel
                                    htmlFor="terms"
                                    className="block leading-5 font-normal text-text-secondary"
                                >
                                    Я принимаю{' '}
                                    <span className="text-primary">
                                        условия использования
                                    </span>{' '}
                                    и{' '}
                                    <span className="text-primary">
                                        политику конфиденциальности
                                    </span>
                                </FieldLabel>
                                <FieldError>{form.errors.terms}</FieldError>
                            </div>
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
                            Создать аккаунт
                        </Button>
                    </FieldGroup>
                </form>

                <p className="mt-4 text-sm leading-5 text-muted-foreground">
                    После регистрации подключите кабинет Wildberries.
                </p>
                <p className="mt-8 text-sm text-muted-foreground">
                    Уже есть аккаунт?{' '}
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
