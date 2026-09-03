import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, MailCheck } from 'lucide-react';

import { AuthHeading } from '@/components/auth/auth-heading';
import { AuthStatus } from '@/components/auth/auth-status';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import { AuthLayout } from '@/layouts/auth-layout';

type VerifyEmailProps = {
    email: string;
    status?: string;
};

export default function VerifyEmail({ email, status }: VerifyEmailProps) {
    const resend = useForm({});
    const logout = useForm({});

    return (
        <>
            <Head title="Подтверждение почты" />
            <AuthLayout>
                <div className="mb-7 flex size-12 items-center justify-center rounded-xl bg-info-soft text-info">
                    <MailCheck />
                </div>
                <AuthHeading
                    title="Подтвердите почту"
                    description={`Мы отправили письмо на ${email}. Перейдите по ссылке, чтобы продолжить.`}
                />

                <div className="mt-8">
                    <FieldGroup className="gap-5">
                        {status ? (
                            <AuthStatus variant="success">{status}</AuthStatus>
                        ) : (
                            <AuthStatus>
                                Сначала подтвердите email, затем подключите
                                кабинет Wildberries.
                            </AuthStatus>
                        )}

                        <Button
                            type="button"
                            className="w-full"
                            onClick={() =>
                                resend.post('/email/verification-notification')
                            }
                            disabled={resend.processing}
                        >
                            {resend.processing ? (
                                <LoaderCircle
                                    data-icon="inline-start"
                                    className="animate-spin"
                                />
                            ) : null}
                            Отправить письмо повторно
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            className="w-full"
                            onClick={() => logout.post('/logout')}
                            disabled={logout.processing}
                        >
                            Выйти из аккаунта
                        </Button>
                    </FieldGroup>
                </div>
            </AuthLayout>
        </>
    );
}
