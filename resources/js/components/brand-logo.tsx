import { Link } from '@inertiajs/react';

type BrandLogoProps = {
    compact?: boolean;
    href?: string;
};

export function BrandLogo({ compact = false, href = '/' }: BrandLogoProps) {
    return (
        <Link
            href={href}
            className="inline-flex min-h-11 items-center rounded-md outline-none focus-visible:ring-[3px] focus-visible:ring-ring/20"
            aria-label="SellerScope"
        >
            <img
                src={
                    compact
                        ? '/images/brand/sellerscope-mark.png'
                        : '/images/brand/sellerscope-logo-horizontal.png'
                }
                alt=""
                width={compact ? 36 : 154}
                height={36}
                className={
                    compact
                        ? 'size-9 object-contain'
                        : 'h-9 w-auto object-contain'
                }
            />
        </Link>
    );
}
