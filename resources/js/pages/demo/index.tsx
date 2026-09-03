import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    LayoutDashboard,
    LockKeyhole,
    Settings,
} from 'lucide-react';

import { BrandLogo } from '@/components/brand-logo';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const demos = [
    {
        title: 'Авторизация',
        description: 'AuthLayout и форма входа',
        href: '/demo/auth',
        icon: LockKeyhole,
    },
    {
        title: 'Каркас приложения',
        description: 'AppLayout, навигация и topbar',
        href: '/demo/app',
        icon: LayoutDashboard,
    },
    {
        title: 'Настройки',
        description: 'SettingsLayout и базовые primitives',
        href: '/demo/settings',
        icon: Settings,
    },
] as const;

export default function DemoIndex() {
    return (
        <>
            <Head title="UI foundations" />
            <main className="min-h-screen bg-surface-subtle px-5 py-8 sm:px-8 sm:py-12">
                <div className="mx-auto max-w-[1040px]">
                    <BrandLogo />
                    <div className="mt-16 max-w-2xl">
                        <p className="text-sm font-semibold text-primary">
                            Demo-MVP · Этап 4
                        </p>
                        <h1 className="mt-3 text-[30px] leading-[38px] font-semibold tracking-normal sm:text-[38px] sm:leading-[46px]">
                            Основа интерфейса SellerScope
                        </h1>
                        <p className="mt-4 text-base leading-6 text-text-secondary">
                            Design tokens, адаптированные shadcn-компоненты и
                            три layout-сценария до подключения бизнес-функций.
                        </p>
                    </div>

                    <div className="mt-10 grid gap-4 md:grid-cols-3">
                        {demos.map((demo) => {
                            const Icon = demo.icon;

                            return (
                                <Card key={demo.title} className="gap-4">
                                    <CardHeader>
                                        <span className="flex size-11 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                                            <Icon className="size-5" />
                                        </span>
                                        <CardTitle className="mt-3 text-lg leading-6">
                                            {demo.title}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="flex flex-1 flex-col">
                                        <p className="text-sm leading-5 text-muted-foreground">
                                            {demo.description}
                                        </p>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="mt-6 justify-between"
                                        >
                                            <Link href={demo.href}>
                                                Открыть
                                                <ArrowRight />
                                            </Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </div>
            </main>
        </>
    );
}
