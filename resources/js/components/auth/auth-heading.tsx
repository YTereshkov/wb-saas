import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

type AuthHeadingProps = {
    title: string;
    description: string;
    backToLogin?: boolean;
};

export function AuthHeading({
    title,
    description,
    backToLogin = false,
}: AuthHeadingProps) {
    return (
        <header>
            {backToLogin ? (
                <Link
                    href="/login"
                    className="mb-10 inline-flex items-center gap-2 rounded-sm text-sm font-medium text-primary outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/20"
                >
                    <ArrowLeft data-icon="inline-start" />
                    Вернуться ко входу
                </Link>
            ) : null}
            <h1 className="text-[30px] leading-[38px] font-semibold tracking-normal">
                {title}
            </h1>
            <p className="mt-2 text-sm leading-5 text-text-secondary">
                {description}
            </p>
        </header>
    );
}
