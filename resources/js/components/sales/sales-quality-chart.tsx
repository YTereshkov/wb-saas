import { useMemo, useState } from 'react';

import { cn } from '@/lib/utils';
import type { ChartPoint } from '@/types';

const number = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 });

function value(point: ChartPoint, key: string) {
    const result = point[key];

    return typeof result === 'number' ? result : null;
}

function path(points: ChartPoint[], key: string, min: number, max: number) {
    let active = false;

    return points
        .map((point, index) => {
            const current = value(point, key);

            if (current === null) {
                active = false;

                return '';
            }

            const x =
                points.length === 1 ? 0 : (index / (points.length - 1)) * 720;
            const y = 220 - ((current - min) / Math.max(1, max - min)) * 220;
            const command = active ? 'L' : 'M';
            active = true;

            return `${command} ${x.toFixed(1)} ${Math.min(220, Math.max(0, y)).toFixed(1)}`;
        })
        .filter(Boolean)
        .join(' ');
}

export function SalesQualityChart({
    day,
    week,
    points: preparedPoints,
    defaultGranularity = 'day',
}: {
    day: ChartPoint[];
    week: ChartPoint[];
    points?: ChartPoint[];
    defaultGranularity?: 'day' | 'week' | 'month';
}) {
    const [granularity, setGranularity] = useState<'day' | 'week'>('day');
    const [hovered, setHovered] = useState<number | null>(null);
    const fixedGranularity = defaultGranularity !== 'day';
    const points = fixedGranularity
        ? (preparedPoints ?? day)
        : granularity === 'day'
          ? day
          : week;
    const returnsMax = useMemo(
        () =>
            Math.max(
                5,
                ...points.flatMap((point) => [
                    value(point, 'returnsRate') ?? 0,
                    value(point, 'comparisonReturnsRate') ?? 0,
                ]),
            ) * 1.15,
        [points],
    );
    const buyoutValues = points.flatMap((point) => [
        value(point, 'buyout') ?? 100,
        value(point, 'comparisonBuyout') ?? 100,
    ]);
    const buyoutMin =
        buyoutValues.length === 0
            ? 0
            : Math.max(0, Math.floor(Math.min(...buyoutValues) / 5) * 5 - 5);
    const hoveredPoint = hovered === null ? null : points[hovered];
    const series = [
        {
            key: 'buyout',
            label: 'Выкуп',
            color: '#5527ff',
            dashed: false,
            axis: 'buyout',
        },
        {
            key: 'comparisonBuyout',
            label: 'Выкуп, прошлый период',
            color: '#8d72ff',
            dashed: true,
            axis: 'buyout',
        },
        {
            key: 'returnsRate',
            label: 'Возвраты',
            color: '#f01f2d',
            dashed: false,
            axis: 'returns',
        },
        {
            key: 'comparisonReturnsRate',
            label: 'Возвраты, прошлый период',
            color: '#fb7185',
            dashed: true,
            axis: 'returns',
        },
    ];

    return (
        <section className="rounded-lg border bg-background p-5 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 className="text-base font-semibold">
                        Выкуп и возвраты
                    </h2>
                    <div className="mt-2 flex max-w-3xl flex-wrap gap-x-4 gap-y-2 text-xs text-text-secondary">
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
                {fixedGranularity ? (
                    <span className="rounded-md bg-secondary px-3 py-2 text-xs font-medium text-text-secondary">
                        {defaultGranularity === 'month'
                            ? 'По месяцам'
                            : 'По неделям'}
                    </span>
                ) : (
                    <div className="flex rounded-md bg-secondary p-1">
                        {(['day', 'week'] as const).map((item) => (
                            <button
                                key={item}
                                type="button"
                                className={cn(
                                    'h-8 rounded px-3 text-xs font-medium',
                                    granularity === item &&
                                        'bg-background shadow-sm',
                                )}
                                onClick={() => setGranularity(item)}
                            >
                                {item === 'day' ? 'По дням' : 'По неделям'}
                            </button>
                        ))}
                    </div>
                )}
            </div>
            <div className="relative mt-5 h-[260px] px-10">
                <div className="absolute inset-y-0 left-0 flex flex-col justify-between pb-7 text-[10px] text-primary">
                    {[1, 2 / 3, 1 / 3, 0].map((ratio, index) => (
                        <span key={`buyout-${index}`}>
                            {number.format(
                                buyoutMin + (100 - buyoutMin) * ratio,
                            )}
                            %
                        </span>
                    ))}
                </div>
                <div className="absolute inset-y-0 right-0 flex flex-col justify-between pb-7 text-[10px] text-destructive">
                    {[returnsMax, returnsMax * 0.66, returnsMax * 0.33, 0].map(
                        (item, index) => (
                            <span key={`returns-${index}`}>
                                {number.format(item)}%
                            </span>
                        ),
                    )}
                </div>
                <svg
                    viewBox="0 0 720 250"
                    preserveAspectRatio="none"
                    className="h-full w-full"
                    role="img"
                    aria-label="Динамика выкупа и возвратов"
                    onPointerLeave={() => setHovered(null)}
                    onPointerMove={(event) => {
                        const box = event.currentTarget.getBoundingClientRect();
                        setHovered(
                            Math.round(
                                Math.max(
                                    0,
                                    Math.min(
                                        1,
                                        (event.clientX - box.left) / box.width,
                                    ),
                                ) *
                                    (points.length - 1),
                            ),
                        );
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
                    {series.map((item) => (
                        <path
                            key={item.key}
                            d={path(
                                points,
                                item.key,
                                item.axis === 'buyout' ? buyoutMin : 0,
                                item.axis === 'buyout' ? 100 : returnsMax,
                            )}
                            fill="none"
                            stroke={item.color}
                            strokeWidth="2"
                            strokeDasharray={item.dashed ? '5 5' : undefined}
                            vectorEffect="non-scaling-stroke"
                        />
                    ))}
                    {points.map((point, index) => {
                        const show =
                            index === 0 ||
                            index === points.length - 1 ||
                            (index < points.length - 2 &&
                                index %
                                    Math.max(
                                        1,
                                        Math.floor(points.length / 5),
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
                    <div className="pointer-events-none absolute top-3 left-1/2 z-10 min-w-44 -translate-x-1/2 rounded-md border bg-background p-3 text-xs shadow-lg">
                        <p className="font-semibold">{hoveredPoint.label}</p>
                        {series.map((item) => (
                            <p
                                key={item.key}
                                className="mt-1 flex justify-between gap-4"
                            >
                                <span>{item.label}</span>
                                <strong>
                                    {value(hoveredPoint, item.key) === null
                                        ? '—'
                                        : `${number.format(value(hoveredPoint, item.key) ?? 0)}%`}
                                </strong>
                            </p>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}
