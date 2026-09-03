import { cn } from '@/lib/utils';

export function ToggleSwitch({
    checked,
    onCheckedChange,
    disabled,
    label,
}: {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    disabled?: boolean;
    label: string;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            disabled={disabled}
            onClick={() => onCheckedChange(!checked)}
            className={cn(
                'relative h-6 w-11 shrink-0 rounded-full border border-transparent bg-muted-foreground/35 outline-none transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/25 disabled:cursor-not-allowed disabled:opacity-50',
                checked && 'bg-primary',
            )}
        >
            <span
                className={cn(
                    'absolute top-0.5 left-0.5 size-[18px] rounded-full bg-white shadow-sm transition-transform',
                    checked && 'translate-x-5',
                )}
            />
        </button>
    );
}
