import { Check } from 'lucide-react';

import { cn } from '@/lib/utils';

const steps = [
    { number: 1, label: 'Токен' },
    { number: 2, label: 'Проверка' },
    { number: 3, label: 'Загрузка данных' },
];

export function ConnectionStepper({ current }: { current: 1 | 2 | 3 }) {
    return (
        <ol className="grid grid-cols-3 px-5 py-6 sm:px-16 lg:px-40">
            {steps.map((step, index) => {
                const completed = step.number < current;
                const active = step.number === current;

                return (
                    <li
                        key={step.number}
                        className="relative flex items-center gap-3"
                    >
                        {index > 0 && (
                            <span className="absolute top-4 right-[calc(100%+10px)] left-[-65%] h-px bg-border sm:left-[-72%]" />
                        )}
                        <span
                            className={cn(
                                'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full border border-[#93a0b7] bg-background text-sm text-text-secondary',
                                active &&
                                    'border-primary bg-primary text-primary-foreground',
                                completed &&
                                    'border-success bg-success-soft text-success',
                            )}
                        >
                            {completed ? (
                                <Check className="size-4" />
                            ) : (
                                step.number
                            )}
                        </span>
                        <span
                            className={cn(
                                'sr-only text-sm text-text-secondary sm:not-sr-only sm:block',
                                active && 'font-medium text-primary',
                            )}
                        >
                            {step.label}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}
