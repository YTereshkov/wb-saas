import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type DateRange = { start: string; end: string };

type Props = {
    min: string;
    max: string;
    value: DateRange;
    onChange: (value: DateRange) => void;
    onApply: () => void;
    onCancel: () => void;
};

const weekdays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const monthFormatter = new Intl.DateTimeFormat('ru-RU', {
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
});

function parseIso(value: string) {
    return new Date(`${value}T00:00:00Z`);
}

function iso(date: Date) {
    return date.toISOString().slice(0, 10);
}

function monthStart(date: Date) {
    return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1));
}

function addMonths(date: Date, count: number) {
    return new Date(
        Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + count, 1),
    );
}

function monthDays(month: Date) {
    const first = monthStart(month);
    const offset = (first.getUTCDay() + 6) % 7;
    const days = new Date(
        Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + 1, 0),
    ).getUTCDate();

    return Array.from({ length: 42 }, (_, index) => {
        const day = index - offset + 1;

        return day < 1 || day > days
            ? null
            : new Date(
                  Date.UTC(first.getUTCFullYear(), first.getUTCMonth(), day),
              );
    });
}

function initialMonth(value: DateRange, max: string) {
    return monthStart(parseIso(value.start || max));
}

function CalendarMonth({
    month,
    min,
    max,
    value,
    onSelect,
    className,
}: {
    month: Date;
    min: string;
    max: string;
    value: DateRange;
    onSelect: (date: string) => void;
    className?: string;
}) {
    const days = useMemo(() => monthDays(month), [month]);

    return (
        <div className={cn('min-w-0 flex-1', className)}>
            <p className="text-center text-sm font-semibold capitalize">
                {monthFormatter.format(month)}
            </p>
            <div className="mt-4 grid grid-cols-7 gap-1" aria-hidden="true">
                {weekdays.map((day) => (
                    <span
                        key={day}
                        className="grid h-7 place-items-center text-xs text-muted-foreground"
                    >
                        {day}
                    </span>
                ))}
            </div>
            <div className="grid grid-cols-7 gap-1">
                {days.map((date, index) => {
                    if (date === null) {
                        return (
                            <span key={`empty-${index}`} className="size-9" />
                        );
                    }

                    const dateIso = iso(date);
                    const disabled = dateIso < min || dateIso > max;
                    const selected =
                        dateIso === value.start || dateIso === value.end;
                    const inRange =
                        value.start !== '' &&
                        value.end !== '' &&
                        dateIso > value.start &&
                        dateIso < value.end;

                    return (
                        <button
                            key={dateIso}
                            type="button"
                            className={cn(
                                'grid size-9 place-items-center rounded-md text-sm outline-none hover:bg-secondary focus-visible:ring-[3px] focus-visible:ring-ring/20 disabled:pointer-events-none disabled:text-muted-foreground/40',
                                inRange &&
                                    'rounded-none bg-accent text-accent-foreground',
                                selected &&
                                    'bg-primary text-primary-foreground hover:bg-primary',
                            )}
                            disabled={disabled}
                            aria-label={new Intl.DateTimeFormat('ru-RU', {
                                dateStyle: 'long',
                                timeZone: 'UTC',
                            }).format(date)}
                            aria-pressed={selected}
                            onClick={() => onSelect(dateIso)}
                        >
                            {date.getUTCDate()}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

export function DateRangePicker({
    min,
    max,
    value,
    onChange,
    onApply,
    onCancel,
}: Props) {
    const [visibleMonth, setVisibleMonth] = useState(() =>
        initialMonth(value, max),
    );
    const invalid =
        value.start === '' ||
        value.end === '' ||
        value.start < min ||
        value.end > max ||
        value.start > value.end;
    const firstAvailableMonth = monthStart(parseIso(min));
    const lastAvailableMonth = monthStart(parseIso(max));
    const canGoBack = visibleMonth > firstAvailableMonth;
    const canGoForward = visibleMonth < lastAvailableMonth;
    const showPreviousAsCompanion =
        addMonths(visibleMonth, 1) > lastAvailableMonth &&
        visibleMonth > firstAvailableMonth;
    const companionMonth = showPreviousAsCompanion
        ? addMonths(visibleMonth, -1)
        : addMonths(visibleMonth, 1);

    const selectDate = (date: string) => {
        if (value.start === '' || value.end !== '' || date < value.start) {
            onChange({ start: date, end: '' });

            return;
        }

        onChange({ start: value.start, end: date });
    };

    return (
        <div className="flex flex-col gap-5">
            <div className="flex items-center justify-between">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Предыдущий месяц"
                    disabled={!canGoBack}
                    onClick={() =>
                        setVisibleMonth((month) => addMonths(month, -1))
                    }
                >
                    <ChevronLeft />
                </Button>
                <p className="text-sm text-muted-foreground">
                    Доступно: {min.split('-').reverse().join('.')}–
                    {max.split('-').reverse().join('.')}
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Следующий месяц"
                    disabled={!canGoForward}
                    onClick={() =>
                        setVisibleMonth((month) => addMonths(month, 1))
                    }
                >
                    <ChevronRight />
                </Button>
            </div>

            <div className="flex gap-6">
                <CalendarMonth
                    month={visibleMonth}
                    min={min}
                    max={max}
                    value={value}
                    onSelect={selectDate}
                    className={showPreviousAsCompanion ? 'md:order-2' : ''}
                />
                <CalendarMonth
                    month={companionMonth}
                    min={min}
                    max={max}
                    value={value}
                    onSelect={selectDate}
                    className={cn(
                        'hidden md:block',
                        showPreviousAsCompanion ? 'md:order-1' : 'md:order-2',
                    )}
                />
            </div>

            <FieldGroup className="grid gap-3 sm:grid-cols-2">
                <Field data-invalid={value.start !== '' && value.start < min}>
                    <FieldLabel htmlFor="analytics-period-start">
                        Начало периода
                    </FieldLabel>
                    <Input
                        id="analytics-period-start"
                        type="date"
                        min={min}
                        max={max}
                        value={value.start}
                        aria-invalid={value.start !== '' && value.start < min}
                        onChange={(event) => {
                            onChange({ ...value, start: event.target.value });

                            if (event.target.value) {
                                setVisibleMonth(
                                    monthStart(parseIso(event.target.value)),
                                );
                            }
                        }}
                    />
                </Field>
                <Field data-invalid={value.end !== '' && value.end > max}>
                    <FieldLabel htmlFor="analytics-period-end">
                        Конец периода
                    </FieldLabel>
                    <Input
                        id="analytics-period-end"
                        type="date"
                        min={value.start || min}
                        max={max}
                        value={value.end}
                        aria-invalid={value.end !== '' && value.end > max}
                        onChange={(event) =>
                            onChange({ ...value, end: event.target.value })
                        }
                    />
                </Field>
            </FieldGroup>
            {invalid && value.start !== '' && value.end !== '' ? (
                <FieldError>
                    Выберите даты внутри доступного диапазона. Начало не может
                    быть позже конца.
                </FieldError>
            ) : null}
            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button type="button" variant="outline" onClick={onCancel}>
                    Отмена
                </Button>
                <Button type="button" disabled={invalid} onClick={onApply}>
                    Применить
                </Button>
            </div>
        </div>
    );
}
