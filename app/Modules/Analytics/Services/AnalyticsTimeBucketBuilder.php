<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Data\AnalyticsPeriod;
use Carbon\CarbonImmutable;

final class AnalyticsTimeBucketBuilder
{
    /** @return list<array{start: CarbonImmutable, end: CarbonImmutable, comparisonStart: CarbonImmutable, comparisonEnd: CarbonImmutable, label: string}> */
    public function forPeriod(AnalyticsPeriod $period): array
    {
        $buckets = [];
        $cursor = $period->start;

        while ($cursor->lte($period->end)) {
            $end = $this->bucketEnd($cursor, $period)->min($period->end);
            $offset = (int) $period->start->diffInDays($cursor);
            $length = (int) $cursor->diffInDays($end) + 1;
            $comparisonStart = $period->comparisonStart->addDays($offset);

            $buckets[] = [
                'start' => $cursor,
                'end' => $end,
                'comparisonStart' => $comparisonStart,
                'comparisonEnd' => $comparisonStart->addDays($length - 1)->min($period->comparisonEnd),
                'label' => $this->label($cursor, $end, $period->granularity),
            ];
            $cursor = $end->addDay();
        }

        return $buckets;
    }

    private function bucketEnd(CarbonImmutable $start, AnalyticsPeriod $period): CarbonImmutable
    {
        return match ($period->granularity) {
            'month' => $start->endOfMonth()->startOfDay(),
            'week' => $start->endOfWeek()->startOfDay(),
            default => $start,
        };
    }

    private function label(CarbonImmutable $start, CarbonImmutable $end, string $granularity): string
    {
        if ($granularity === 'day') {
            return $start->format('d.m');
        }

        if ($granularity === 'week') {
            return $start->format('d.m').'–'.$end->format('d.m');
        }

        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];

        return $start->day.'–'.$end->day.' '.$months[$start->month].' '.$start->year;
    }
}
