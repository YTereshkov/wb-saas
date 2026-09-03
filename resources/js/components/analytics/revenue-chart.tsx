import { useMemo, useState } from 'react';

import { cn } from '@/lib/utils';

type Point = { label: string; current: number; comparison: number | null };

function pathFor(points: Point[], key: 'current' | 'comparison', max: number) {
    const width = 720;
    const height = 220;

    let hasActiveSegment = false;

    return points
        .map((point, index) => {
            const value = point[key];

            if (value === null) {
                hasActiveSegment = false;

                return '';
            }

            const x =
                points.length === 1 ? 0 : (index / (points.length - 1)) * width;
            const y = height - (value / max) * height;
            const command = hasActiveSegment ? 'L' : 'M';
            hasActiveSegment = true;

            return `${command} ${x.toFixed(1)} ${y.toFixed(1)}`;
        })
        .filter(Boolean)
        .join(' ');
}

const money = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
});

function axisLabel(value: number) {
    const rubles = value / 100;

    if (rubles >= 1_000_000) {
        return `${(rubles / 1_000_000).toFixed(1).replace('.0', '')} млн`;
    }

    return `${Math.round(rubles / 1_000)} тыс.`;
}

export function RevenueChart({
    day,
    week,
    points: selectedPoints,
    defaultGranularity = 'day',
}: {
    day: Point[];
    week: Point[];
    points?: Point[];
    defaultGranularity?: 'day' | 'week' | 'month';
}) {
    const [granularity, setGranularity] = useState<'day' | 'week'>('day');
    const [hovered, setHovered] = useState<number | null>(null);
    const points = useMemo(
        () =>
            defaultGranularity === 'day'
                ? granularity === 'day'
                    ? day
                    : week
                : (selectedPoints ?? []),
        [day, defaultGranularity, granularity, selectedPoints, week],
    );
    const max = useMemo(
        () =>
            Math.max(
                1,
                ...points.flatMap((point) => [
                    point.current,
                    point.comparison ?? 0,
                ]),
            ) * 1.08,
        [points],
    );
    const hoveredPoint = hovered === null ? null : points[hovered];
    const hoveredX =
        hovered === null || points.length === 1
            ? 0
            : (hovered / (points.length - 1)) * 720;

    return (
        <section className="rounded-lg border border-border bg-background p-5 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 className="text-base font-semibold">
                        Динамика выручки
                    </h2>
                    <div className="mt-2 flex flex-wrap items-center gap-4 text-xs text-text-secondary">
                        <span className="inline-flex items-center gap-2">
                            <span className="h-0.5 w-5 bg-primary" />
                            Текущий период
                        </span>
                        <span className="inline-flex items-center gap-2">
                            <span className="h-0.5 w-5 border-t-2 border-dashed border-[#9ca3b0]" />
                            Прошлый период
                        </span>
                    </div>
                </div>
                {defaultGranularity === 'day' ? (
                    <div
                        className="flex rounded-md bg-secondary p-1"
                        aria-label="Группировка графика"
                    >
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
                ) : (
                    <span className="rounded-md bg-secondary px-3 py-2 text-xs font-medium text-text-secondary">
                        {defaultGranularity === 'month'
                            ? 'По месяцам'
                            : 'По неделям'}
                    </span>
                )}
            </div>

            <div className="relative mt-6 h-[250px] pl-12">
                <div className="absolute inset-y-0 left-0 flex w-10 flex-col justify-between pb-6 text-right text-[10px] text-muted-foreground">
                    {[max, max * 0.75, max * 0.5, max * 0.25, 0].map(
                        (value) => (
                            <span key={value}>{axisLabel(value)}</span>
                        ),
                    )}
                </div>
                <svg
                    viewBox="0 0 720 250"
                    preserveAspectRatio="none"
                    className="h-full w-full overflow-visible"
                    role="img"
                    aria-label="График выручки текущего и прошлого периода"
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
                            strokeWidth="1"
                        />
                    ))}
                    <path
                        d={pathFor(points, 'comparison', max)}
                        fill="none"
                        stroke="#9ca3b0"
                        strokeWidth="2"
                        strokeDasharray="6 6"
                        vectorEffect="non-scaling-stroke"
                    />
                    <path
                        d={pathFor(points, 'current', max)}
                        fill="none"
                        stroke="#5527ff"
                        strokeWidth="2.5"
                        vectorEffect="non-scaling-stroke"
                    />
                    {hovered !== null && hoveredPoint && (
                        <>
                            <line
                                x1={hoveredX}
                                x2={hoveredX}
                                y1="0"
                                y2="220"
                                stroke="#dde1e8"
                            />
                            <circle
                                cx={hoveredX}
                                cy={220 - (hoveredPoint.current / max) * 220}
                                r="4"
                                fill="#5527ff"
                            />
                        </>
                    )}
                    {points.map((point, index) => {
                        const show =
                            index === 0 ||
                            index === points.length - 1 ||
                            index %
                                Math.max(1, Math.floor(points.length / 4)) ===
                                0;

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
                        className="pointer-events-none absolute top-2 z-10 min-w-44 rounded-lg border bg-background p-3 text-xs shadow-lg"
                        style={{
                            left: `${Math.min(78, Math.max(4, (hoveredX / 720) * 100))}%`,
                        }}
                    >
                        <p className="font-semibold">{hoveredPoint.label}</p>
                        <p className="mt-1 text-primary">
                            Текущий: {money.format(hoveredPoint.current / 100)}
                        </p>
                        <p className="mt-1 text-text-secondary">
                            Прошлый:{' '}
                            {hoveredPoint.comparison === null
                                ? 'Нет данных'
                                : money.format(hoveredPoint.comparison / 100)}
                        </p>
                    </div>
                )}
            </div>
        </section>
    );
}
