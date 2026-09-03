import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle2, Eye, EyeOff, Info, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

import { ConnectionShell } from '@/components/settings/connection-shell';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';

export default function ConnectionToken() {
    const [visible, setVisible] = useState(false);
    const form = useForm({ token: '' });

    return (
        <>
            <Head title="Подключение кабинета" />
            <ConnectionShell current={1}>
                <div className="grid lg:grid-cols-[1.25fr_.9fr]">
                    <form
                        className="p-6 lg:p-7"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/settings/cabinets/connect/verify');
                        }}
                    >
                        <FieldGroup>
                            <div>
                                <h3 className="text-lg font-semibold">
                                    1. Создайте токен в кабинете Wildberries
                                </h3>
                                <p className="mt-2 text-sm text-text-secondary">
                                    Откройте настройки API и создайте отдельный
                                    токен для SellerScope.
                                </p>
                            </div>
                            <Field data-invalid={Boolean(form.errors.token)}>
                                <FieldLabel htmlFor="token">
                                    2. Вставьте токен
                                </FieldLabel>
                                <div className="relative">
                                    <Input
                                        id="token"
                                        type={visible ? 'text' : 'password'}
                                        value={form.data.token}
                                        onChange={(event) =>
                                            form.setData(
                                                'token',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            form.errors.token,
                                        )}
                                        autoComplete="off"
                                        className="pr-12"
                                    />
                                    <button
                                        type="button"
                                        className="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-text-secondary"
                                        aria-label={
                                            visible
                                                ? 'Скрыть токен'
                                                : 'Показать токен'
                                        }
                                        onClick={() =>
                                            setVisible((value) => !value)
                                        }
                                    >
                                        {visible ? <EyeOff /> : <Eye />}
                                    </button>
                                </div>
                                <FieldDescription>
                                    Рабочему токену нужны Content и Statistics;
                                    Analytics — для остатков. Тестовый токен
                                    подключится к песочнице без остатков.
                                </FieldDescription>
                                <FieldError>{form.errors.token}</FieldError>
                            </Field>
                            <Alert className="border-info/20 bg-info-soft">
                                <Info className="text-info" />
                                <AlertDescription>
                                    WB ID определим автоматически. Название
                                    кабинета можно изменить позже в настройках.
                                </AlertDescription>
                            </Alert>
                            <div className="flex flex-wrap gap-3">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing
                                        ? 'Проверяем…'
                                        : 'Проверить и продолжить'}
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href="/settings/cabinets">
                                        Отмена
                                    </Link>
                                </Button>
                            </div>
                        </FieldGroup>
                    </form>
                    <aside className="border-t border-border-subtle p-6 lg:border-t-0 lg:border-l lg:p-8">
                        <div className="flex items-center gap-3">
                            <ShieldCheck className="size-8 text-text-secondary" />
                            <h3 className="text-lg font-semibold">
                                Безопасное подключение
                            </h3>
                        </div>
                        <ul className="mt-7 space-y-5 text-sm text-text-secondary">
                            {[
                                'Токен хранится в зашифрованном виде',
                                'SellerScope использует данные только для аналитики',
                                'Доступ можно отозвать в любой момент',
                            ].map((item) => (
                                <li key={item} className="flex gap-3">
                                    <CheckCircle2 className="size-5 shrink-0 text-success" />
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </aside>
                </div>
            </ConnectionShell>
        </>
    );
}
