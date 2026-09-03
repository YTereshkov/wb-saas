import { useMemo, useState } from 'react';

import { cn } from '@/lib/utils';
import type { ChartPoint } from '@/types';

type Series = {
    key: string;
    label: string;
    color: string;
    dashed?: boolean;
};

type Props = {
    title: string;
    day: ChartPoint[];
    week?: ChartPoint[];
    points?: ChartPoint[];
    defaultGranularity?: 'day' | 'week' | 'month';
    series: Series[];
    format?: 'money' | 'number' | 'percent';
    height?: number;
};

const number = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 });
const money = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
});

function numeric(point: ChartPoint, key: string) {
    const value = point[key];

    return typeof value === 'number' ? value : null;
}

function pathFor(points: ChartPoint[], key: string, min: number, max: number) {
    let active = false;
    const range = max - min;

    return points
        .map((point, index) => {
            const value = numeric(point, key);

            if (value === null) {
                active = false;

                return '';
            }

            const x =
                points.length === 1 ? 0 : (index / (points.length - 1)) * 720;
            const y = 220 - ((value - min) / range) * 220;
            const command = active ? 'L' : 'M';
            active = true;

            return `${command} ${x.toFixed(1)} ${y.toFixed(1)}`;
        })
        .filter(Boolean)
        .join(' ');
}

function chartBounds(points: ChartPoint[], series: Series[]) {
    const values = points.flatMap((point) =>
        series.flatMap((item) => {
            const value = numeric(point, item.key);

            return value === null ? [] : [value];
        }),
    );
    const rawMin = Math.min(0, ...values);
    const rawMax = Math.max(0, ...values);
    const dataRange = rawMax - rawMin || 1;
    const padding = dataRange * 0.08;

    return {
        min: rawMin < 0 ? rawMin - padding : 0,
        max: rawMax > 0 ? rawMax + padding : 1,
    };
}

function label(value: number, format: Props['format']) {
    if (format === 'money') {
        const rubles = value / 100;

        return rubles >= 1_000_000
            ? `${number.format(rubles / 1_000_000)} млн`
            : `${number.format(rubles / 1_000)} тыс.`;
    }

    return `${number.format(value)}${format === 'percent' ? '%' : ''}`;
}

function tooltipValue(value: number | null, format: Props['format']) {
    if (value === null) {
        return 'Нет данных';
    }

    if (format === 'money') {
        return money.format(value / 100);
    }

    return `${number.format(value)}${format === 'percent' ? '%' : ''}`;
}

export function MultiLineChart({
    title,
    day,
    week,
    points: preparedPoints,
    defaultGranularity = 'day',
    series,
    format = 'number',
    height = 250,
}: Props) {
    const [granularity, setGranularity] = useState<'day' | 'week'>('day');
    const [hovered, setHovered] = useState<number | null>(null);
    const fixedGranularity = defaultGranularity !== 'day';
    const points = fixedGranularity
        ? (preparedPoints ?? day)
        : granularity === 'week' && week
          ? week
          : day;
    const bounds = useMemo(() => chartBounds(points, series), [points, series]);
    const ticks = [1, 0.75, 0.5, 0.25, 0].map(
        (ratio) => bounds.min + (bounds.max - bounds.min) * ratio,
    );
    const zeroY = 220 - ((0 - bounds.min) / (bounds.max - bounds.min)) * 220;
    const hoveredPoint = hovered === null ? null : points[hovered];
    const hoveredX =
        hovered === null || points.length === 1
            ? 0
            : (hovered / (points.length - 1)) * 720;

    return (
        <section className="rounded-lg border border-border bg-background p-5 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 className="text-base font-semibold">{title}</h2>
                    <div className="mt-2 flex flex-wrap gap-4 text-xs text-text-secondary">
                        {series.map((item) => (
                            <span
                                key={item.key}
                                className="inline-flex items-center gap-2"
                            >
                                <span
                                    className={cn(
                                        'h-0.5 w-5',
                                        item.dashed &&
                                            'border-t-2 border-dashed bg-transparent',
                                    )}
                                    style={
                                        item.dashed
                                            ? { borderColor: item.color }
                                            : { backgroundColor: item.color }
                                    }
                                />
                                {item.label}
                            </span>
                        ))}
                    </div>
                </div>
                {week && !fixedGranularity ? (
                    <div className="flex rounded-md bg-secondary p-1">
                        {(['day', 'week'] as const).map((value) => (
                            <button
                                key={value}
                                type="button"
                                className={cn(
                                    'h-8 rounded px-3 text-xs font-medium text-text-secondary',
                                    granularity === value &&
                                        'bg-background text-foreground shadow-sm',
                                )}
                                onClick={() => {
                                    setGranularity(value);
                                    setHovered(null);
                                }}
                            >
                                {value === 'day' ? 'По дням' : 'По неделям'}
                            </button>
                        ))}
                    </div>
                ) : fixedGranularity ? (
                    <span className="rounded-md bg-secondary px-3 py-2 text-xs font-medium text-text-secondary">
                        {defaultGranularity === 'month'
                            ? 'По месяцам'
                            : 'По неделям'}
                    </span>
                ) : null}
            </div>
            <div className="relative mt-5 pl-12" style={{ height }}>
                <div className="absolute inset-y-0 left-0 flex w-10 flex-col justify-between pb-6 text-right text-[10px] text-muted-foreground">
                    {ticks.map((value, index) => (
                        <span key={index}>{label(value, format)}</span>
                    ))}
                </div>
                <svg
                    viewBox="0 0 720 250"
                    preserveAspectRatio="none"
                    className="h-full w-full overflow-visible"
                    role="img"
                    aria-label={title}
                    onPointerLeave={() => setHovered(null)}
                    onPointerMove={(event) => {
                        const bounds =
                            event.currentTarget.getBoundingClientRect();
                        const ratio = Math.min(
                            1,
                            Math.max(
                                0,
                                (event.clientX - bounds.left) / bounds.width,
                            ),
                        );
                        setHovered(Math.round(ratio * (points.length - 1)));
                    }}
                >
                    {[0, 1, 2, 3, 4].map((line) => (
                        <line
                            key={line}
                            x1="0"
                            x2="720"
                            y1={line * 55}
                            y2={line * 55}
                            stroke="#eceef2"
                        />
                    ))}
                    <line
                        x1="0"
                        x2="720"
                        y1={zeroY}
                        y2={zeroY}
                        stroke="#c7cbd3"
                        strokeWidth="1.2"
                        vectorEffect="non-scaling-stroke"
                    />
                    {series.map((item) => (
                        <path
                            key={item.key}
                            d={pathFor(
                                points,
                                item.key,
                                bounds.min,
                                bounds.max,
                            )}
                            fill="none"
                            stroke={item.color}
                            strokeWidth="2.2"
                            strokeDasharray={item.dashed ? '6 6' : undefined}
                            vectorEffect="non-scaling-stroke"
                        />
                    ))}
                    {hovered !== null && (
                        <line
                            x1={hoveredX}
                            x2={hoveredX}
                            y1="0"
                            y2="220"
                            stroke="#dde1e8"
                        />
                    )}
                    {points.map((point, index) => {
                        const show =
                            index === 0 ||
                            index === points.length - 1 ||
                            (index < points.length - 2 &&
                                index %
                                    Math.max(
                                        1,
                                        Math.floor(points.length / 4),
                                    ) ===
                                    0);

                        return show ? (
                            <text
                                key={`${point.label}-${index}`}
                                x={
                                    points.length === 1
                                        ? 0
                                        : (index / (points.length - 1)) * 720
                                }
                                y="245"
                                textAnchor={
                                    index === 0
                                        ? 'start'
                                        : index === points.length - 1
                                          ? 'end'
                                          : 'middle'
                                }
                                fontSize="10"
                                fill="#8a93a5"
                            >
                                {point.label}
                            </text>
                        ) : null;
                    })}
                </svg>
                {hoveredPoint && (
                    <div
                        className="pointer-events-none absolute top-2 z-10 min-w-40 rounded-lg border bg-background p-3 text-xs shadow-lg"
                        style={{
                            left: `${Math.min(76, Math.max(4, (hoveredX / 720) * 100))}%`,
                        }}
                    >
                        <p className="font-semibold">{hoveredPoint.label}</p>
                        {series.map((item) => (
                            <p
                                key={item.key}
                                className="mt-1 text-text-secondary"
                            >
                                {item.label}:{' '}
                                <span style={{ color: item.color }}>
                                    {tooltipValue(
                                        numeric(hoveredPoint, item.key),
                                        format,
                                    )}
                                </span>
                            </p>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}
