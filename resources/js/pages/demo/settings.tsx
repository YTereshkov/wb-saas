import { Head } from '@inertiajs/react';
import { CheckCircle2, Info, RefreshCw } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { SettingsLayout } from '@/layouts/settings-layout';

export default function SettingsDemo() {
    return (
        <>
            <Head title="Настройки" />
            <SettingsLayout>
                <div className="space-y-5">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Подключённый кабинет
                            </CardTitle>
                            <CardDescription>
                                Демонстрация панели без сохранения данных
                            </CardDescription>
                            <CardAction>
                                <Badge variant="success">
                                    <CheckCircle2 />
                                    Подключён
                                </Badge>
                            </CardAction>
                        </CardHeader>
                        <CardContent className="grid gap-5 border-t border-border-subtle pt-6 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                            <div className="space-y-2">
                                <Label htmlFor="cabinet-name">
                                    Название кабинета
                                </Label>
                                <Input
                                    id="cabinet-name"
                                    defaultValue="ООО «Уютный дом»"
                                />
                            </div>
                            <Button variant="outline">
                                <RefreshCw />
                                Синхронизировать
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Состояния компонентов
                            </CardTitle>
                            <CardDescription>
                                Базовые primitives адаптированы под Design
                                System SellerScope
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Tabs defaultValue="status">
                                <TabsList className="w-full justify-start border-b border-border-subtle">
                                    <TabsTrigger value="status">
                                        Статусы
                                    </TabsTrigger>
                                    <TabsTrigger value="message">
                                        Сообщения
                                    </TabsTrigger>
                                </TabsList>
                                <TabsContent value="status" className="pt-6">
                                    <div className="flex flex-wrap gap-2">
                                        <Badge variant="success">Успех</Badge>
                                        <Badge variant="warning">
                                            Внимание
                                        </Badge>
                                        <Badge variant="destructive">
                                            Ошибка
                                        </Badge>
                                        <Badge variant="info">Информация</Badge>
                                        <Badge variant="secondary">
                                            Нейтральный
                                        </Badge>
                                    </div>
                                </TabsContent>
                                <TabsContent value="message" className="pt-6">
                                    <Alert className="border-info/20 bg-info-soft">
                                        <Info className="text-info" />
                                        <AlertTitle>
                                            Информационное сообщение
                                        </AlertTitle>
                                        <AlertDescription className="text-text-secondary">
                                            Здесь будет безопасная подсказка о
                                            состоянии синхронизации.
                                        </AlertDescription>
                                    </Alert>
                                </TabsContent>
                            </Tabs>
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </>
    );
}
