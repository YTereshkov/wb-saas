import { Head, Link } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AuthLayout } from '@/layouts/auth-layout';

export default function AuthDemo() {
    const [passwordVisible, setPasswordVisible] = useState(false);
    const [status, setStatus] = useState<string | null>(null);

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setStatus('Это демонстрация интерфейса. Войдите на рабочей странице.');
    }

    return (
        <>
            <Head title="Вход" />
            <AuthLayout>
                <div>
                    <h1 className="text-[30px] leading-[38px] font-semibold tracking-normal">
                        Войти в SellerScope
                    </h1>
                    <p className="mt-2 text-sm leading-5 text-text-secondary">
                        Продолжите работу с аналитикой магазина.
                    </p>
                </div>

                <form className="mt-8 space-y-5" onSubmit={handleSubmit}>
                    <div className="space-y-2">
                        <Label htmlFor="email">Электронная почта</Label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="email"
                            placeholder="name@company.ru"
                        />
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between gap-4">
                            <Label htmlFor="password">Пароль</Label>
                            <Link
                                href="/forgot-password"
                                className="rounded-sm text-xs font-medium text-primary outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/20"
                            >
                                Забыли пароль?
                            </Link>
                        </div>
                        <div className="relative">
                            <Input
                                id="password"
                                type={passwordVisible ? 'text' : 'password'}
                                autoComplete="current-password"
                                placeholder="Введите пароль"
                                className="pr-12"
                            />
                            <button
                                type="button"
                                onClick={() =>
                                    setPasswordVisible((visible) => !visible)
                                }
                                className="absolute top-0 right-0 flex size-[42px] items-center justify-center rounded-r-md text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/20"
                                aria-label={
                                    passwordVisible
                                        ? 'Скрыть пароль'
                                        : 'Показать пароль'
                                }
                            >
                                {passwordVisible ? <EyeOff /> : <Eye />}
                            </button>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <Checkbox id="remember" />
                        <Label
                            htmlFor="remember"
                            className="font-normal text-text-secondary"
                        >
                            Запомнить меня на этом устройстве
                        </Label>
                    </div>

                    <Button type="submit" className="w-full">
                        Войти
                    </Button>
                    <div className="flex items-center gap-4 text-xs text-muted-foreground">
                        <span className="h-px flex-1 bg-border" />
                        или
                        <span className="h-px flex-1 bg-border" />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        className="w-full"
                        onClick={() =>
                            setStatus(
                                'Одноразовая ссылка отключена. Используйте пароль.',
                            )
                        }
                    >
                        Войти по одноразовой ссылке
                    </Button>
                    {status && (
                        <p
                            role="status"
                            className="text-sm text-text-secondary"
                        >
                            {status}
                        </p>
                    )}
                </form>

                <p className="mt-7 text-center text-sm text-muted-foreground">
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
