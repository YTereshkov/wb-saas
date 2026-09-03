import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';

import { AppSidebar } from '@/components/app-sidebar';
import { AppTopbar } from '@/components/app-topbar';
import { DataAvailabilityNotices } from '@/components/feedback/data-states';

type AppLayoutProps = PropsWithChildren<{
    activeItem?: string;
    settings?: boolean;
    dataResource?: 'stocks';
}>;

export function AppLayout({
    activeItem = 'overview',
    settings = false,
    dataResource,
    children,
}: AppLayoutProps) {
    const context = usePage().props.analyticsContext;

    return (
        <div className="min-h-screen bg-background">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-[76px] border-r border-border-subtle bg-background lg:block xl:w-[216px]">
                <AppSidebar activeItem={activeItem} />
            </aside>

            <div className="min-h-screen lg:pl-[76px] xl:pl-[216px]">
                <AppTopbar activeItem={activeItem} settings={settings} />
                <main className="px-4 py-6 sm:px-6 lg:px-7 lg:py-7 xl:px-8">
                    <DataAvailabilityNotices
                        context={context}
                        activeItem={activeItem}
                        dataResource={dataResource}
                    />
                    {children}
                </main>
            </div>
        </div>
    );
}
