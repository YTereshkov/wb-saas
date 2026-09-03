import { CalendarDays, ChevronDown } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import type { AnalyticsContext } from '@/types';

import { DateRangePicker } from './date-range-picker';

type PeriodChange = {
    periodPreset: string;
    periodStart?: string;
    periodEnd?: string;
};

export function AnalyticsPeriodControl({
    context,
    onChange,
}: {
    context: AnalyticsContext;
    onChange: (change: PeriodChange) => void;
}) {
    const [open, setOpen] = useState(false);
    const [calendar, setCalendar] = useState(false);
    const [range, setRange] = useState({
        start: context.period.start,
        end: context.period.end,
    });
    const coverage = context.coverage.overall;
    const hasCoverage = coverage.start !== null && coverage.end !== null;

    const close = () => {
        setOpen(false);
        setCalendar(false);
    };

    return (
        <div className="relative">
            <Button
                type="button"
                variant="outline"
                size="icon"
                className="lg:hidden"
                aria-label={`Период: ${context.period.label}`}
                aria-expanded={open}
                disabled={!hasCoverage}
                onClick={() => setOpen((value) => !value)}
            >
                <CalendarDays />
            </Button>
            <Button
                type="button"
                variant="outline"
                className="hidden max-w-[250px] justify-between gap-3 font-medium lg:inline-flex"
                aria-expanded={open}
                disabled={!hasCoverage}
                onClick={() => setOpen((value) => !value)}
            >
                <CalendarDays data-icon="inline-start" />
                <span className="truncate">{context.period.label}</span>
                <ChevronDown data-icon="inline-end" />
            </Button>

            {open ? (
                <div
                    className="fixed inset-x-4 top-20 z-50 max-h-[calc(100vh-96px)] overflow-y-auto rounded-lg border border-border bg-background p-2 shadow-lg sm:left-auto sm:w-[360px] lg:absolute lg:top-[calc(100%+8px)] lg:right-0 lg:w-[680px]"
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') {
                            close();
                        }
                    }}
                >
                    {calendar && coverage.start && coverage.end ? (
                        <div className="p-3 sm:p-4">
                            <DateRangePicker
                                min={coverage.start}
                                max={coverage.end}
                                value={range}
                                onChange={setRange}
                                onCancel={close}
                                onApply={() => {
                                    onChange({
                                        periodPreset: 'custom',
                                        periodStart: range.start,
                                        periodEnd: range.end,
                                    });
                                    close();
                                }}
                            />
                        </div>
                    ) : (
                        <div className="flex flex-col gap-1">
                            {context.periodOptions.map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    className="flex min-h-10 w-full items-center rounded-md px-3 text-left text-sm hover:bg-secondary data-[active=true]:bg-accent data-[active=true]:text-accent-foreground"
                                    data-active={
                                        context.period.preset === option.value
                                    }
                                    onClick={() => {
                                        if (option.value === 'custom') {
                                            setRange({
                                                start: context.period.start,
                                                end: context.period.end,
                                            });
                                            setCalendar(true);

                                            return;
                                        }

                                        onChange({
                                            periodPreset: option.value,
                                        });
                                        close();
                                    }}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            ) : null}
        </div>
    );
}
