import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, CheckCircle2 } from 'lucide-react';

import { ProductThumbnail } from '@/components/products/product-thumbnail';
import type { OverviewData } from '@/types';

type Attention = NonNullable<OverviewData['attention']>;

export function AttentionPanel({ attention }: { attention: Attention }) {
    return (
        <section className="rounded-lg border border-border bg-background p-5 sm:p-6">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold">
                        Требуют внимания
                    </h2>
                    <p className="mt-1 text-sm text-text-secondary">
                        Главные сигналы по товарам
                    </p>
                </div>
                <span className="grid size-8 place-items-center rounded-full bg-warning-soft text-warning">
                    <AlertTriangle className="size-4" aria-hidden="true" />
                </span>
            </div>

            {attention.signals.length === 0 ? (
                <div className="mt-8 flex min-h-40 flex-col items-center justify-center text-center">
                    <CheckCircle2 className="size-8 text-success" />
                    <p className="mt-3 text-sm font-semibold">
                        Критичных сигналов нет
                    </p>
                    <p className="mt-1 text-xs text-text-secondary">
                        Продолжайте следить за динамикой.
                    </p>
                </div>
            ) : (
                <div className="mt-5 divide-y divide-border-subtle">
                    {attention.signals.map((signal) => (
                        <Link
                            key={signal.productId}
                            href={signal.href}
                            className="group flex items-center gap-3 py-4 first:pt-0 last:pb-0"
                        >
                            <ProductThumbnail
                                src={signal.imageUrl}
                                className="size-11"
                            />
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-semibold">
                                    {signal.productTitle}
                                </span>
                                <span className="mt-1 block text-xs text-text-secondary">
                                    {signal.risk}
                                </span>
                            </span>
                            <ArrowRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                        </Link>
                    ))}
                </div>
            )}

            <Link
                href="/products/attention"
                className="mt-5 flex h-10 items-center justify-center rounded-md border border-border text-sm font-semibold hover:bg-secondary"
            >
                Все сигналы · {attention.count}
            </Link>
        </section>
    );
}
