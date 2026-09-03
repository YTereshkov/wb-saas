import { CircleAlert, TrendingUp } from 'lucide-react';

import { cn } from '@/lib/utils';

export function InsightBanner({
    children,
    tone = 'accent',
}: {
    children: React.ReactNode;
    tone?: 'accent' | 'warning' | 'success';
}) {
    const Icon = tone === 'warning' ? CircleAlert : TrendingUp;

    return (
        <div
            className={cn(
                'flex items-start gap-3 rounded-lg px-4 py-3.5 text-sm sm:px-5',
                tone === 'accent' && 'bg-accent text-accent-foreground',
                tone === 'warning' && 'bg-warning-soft text-warning',
                tone === 'success' && 'bg-success-soft text-success',
            )}
        >
            <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p className="font-medium">{children}</p>
        </div>
    );
}
