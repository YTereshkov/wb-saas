import { Head } from '@inertiajs/react';

import { SettingsLayout } from '@/layouts/settings-layout';

export default function SettingsPlaceholder({
    tab,
    title,
}: {
    tab: 'notifications' | 'profile';
    title: string;
}) {
    return (
        <>
            <Head title={title} />
            <SettingsLayout activeTab={tab}>
                <div className="rounded-xl border border-border p-6">
                    <h2 className="text-lg font-semibold">{title}</h2>
                    <p className="mt-2 text-sm text-text-secondary">
                        Вкладка уже встроена в единый каркас настроек. Формы
                        будут реализованы на этапе настроек профиля и
                        уведомлений.
                    </p>
                </div>
            </SettingsLayout>
        </>
    );
}
