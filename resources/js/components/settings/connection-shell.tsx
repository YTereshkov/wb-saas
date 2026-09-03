import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { Card } from '@/components/ui/card';
import { SettingsLayout } from '@/layouts/settings-layout';

import { ConnectionStepper } from './connection-stepper';

export function ConnectionShell({
    current,
    children,
}: React.PropsWithChildren<{ current: 1 | 2 | 3 }>) {
    return (
        <SettingsLayout activeTab="cabinets">
            <Link
                href="/settings/cabinets"
                className="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-primary"
            >
                <ArrowLeft className="size-4" />К подключённым кабинетам
            </Link>
            <h2 className="mt-4 text-2xl font-semibold tracking-normal">
                Подключение кабинета Wildberries
            </h2>
            <p className="mt-1 text-sm text-text-secondary">
                Добавьте API-токен — проверим доступы и начнём загрузку данных.
            </p>

            <Card className="mt-5 gap-0 overflow-hidden py-0">
                <ConnectionStepper current={current} />
                <div className="border-t border-border-subtle">{children}</div>
            </Card>
        </SettingsLayout>
    );
}
