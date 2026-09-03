import { Package } from 'lucide-react';

import { cn } from '@/lib/utils';

export function ProductThumbnail({
    src,
    className,
}: {
    src: string | null;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center overflow-hidden rounded-md border border-border-subtle bg-secondary text-muted-foreground',
                className,
            )}
        >
            {src ? (
                <img
                    src={src}
                    alt=""
                    loading="lazy"
                    className="size-full object-cover"
                />
            ) : (
                <Package className="size-1/2" aria-hidden="true" />
            )}
        </span>
    );
}
