import { Head } from '@inertiajs/react';

import { AppLayout } from '@/layouts/app-layout';

export default function SectionPlaceholder({
    section,
    title,
}: {
    section: 'products' | 'sales' | 'stocks';
    title: string;
}) {
    return (
        <>
            <Head title={title} />
            <AppLayout activeItem={section}>
                <div className="mx-auto max-w-[1320px]">
                    <h1 className="text-[30px] leading-[38px] font-semibold tracking-normal">
                        {title}
                    </h1>
                    <p className="mt-2 text-sm text-text-secondary">
                        Раздел подключён к общей навигации. Его аналитические
                        экраны будут реализованы на следующих этапах.
                    </p>
                </div>
            </AppLayout>
        </>
    );
}
