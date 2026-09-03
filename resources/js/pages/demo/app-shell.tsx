import { Head } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

import { DemoProductStrip } from '@/components/demo-product-strip';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { AppLayout } from '@/layouts/app-layout';

const shellSections = [
    { key: 'products', label: 'Товары' },
    { key: 'sales', label: 'Продажи' },
    { key: 'stocks', label: 'Остатки' },
] as const;

export default function AppShellDemo() {
    return (
        <>
            <Head title="Каркас приложения" />
            <AppLayout>
                <div className="mx-auto max-w-[1320px]">
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                        <div>
                            <h1 className="text-[30px] leading-[38px] font-semibold tracking-normal">
                                Обзор
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Демонстрация каркаса без бизнес-данных
                            </p>
                        </div>
                        <Badge variant="info">UI foundations</Badge>
                    </div>

                    <Alert className="mt-6 border-success/20 bg-success-soft text-foreground">
                        <CheckCircle2 className="text-success" />
                        <AlertTitle>Каркас приложения готов</AlertTitle>
                        <AlertDescription className="text-text-secondary">
                            Навигация, кабинет, период и адаптивный shell
                            собраны. Аналитические блоки появятся на следующих
                            этапах.
                        </AlertDescription>
                    </Alert>

                    <section className="mt-6" aria-labelledby="kpi-heading">
                        <h2 id="kpi-heading" className="sr-only">
                            Заглушки ключевых показателей
                        </h2>
                        <div className="grid gap-px overflow-hidden rounded-xl border border-border bg-border sm:grid-cols-2 xl:grid-cols-4">
                            {['Выручка', 'Заказы', 'Продажи', 'Выкуп'].map(
                                (label) => (
                                    <div
                                        key={label}
                                        className="bg-background p-5"
                                    >
                                        <p className="text-xs font-medium text-muted-foreground">
                                            {label}
                                        </p>
                                        <Skeleton className="mt-4 h-8 w-28" />
                                        <Skeleton className="mt-3 h-4 w-20" />
                                    </div>
                                ),
                            )}
                        </div>
                    </section>

                    <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(300px,0.8fr)]">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    Область аналитического контента
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Skeleton className="h-[250px] w-full rounded-lg" />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    Разделы продукта
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {shellSections.map((section) => (
                                    <div
                                        key={section.key}
                                        className="flex items-center justify-between rounded-lg border border-border-subtle p-3"
                                    >
                                        <span className="text-sm font-medium">
                                            {section.label}
                                        </span>
                                        <Badge variant="secondary">
                                            Следующий этап
                                        </Badge>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Легальные demo-изображения товаров
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <DemoProductStrip />
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        </>
    );
}
