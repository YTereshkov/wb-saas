<?php

namespace App\Modules\Wildberries\Normalizers;

use App\Modules\Wildberries\Data\StockData;
use App\Modules\Wildberries\Exceptions\WildberriesApiException;
use Carbon\CarbonImmutable;
use Throwable;
use ZipArchive;

final class HistoricalStockCsvNormalizer
{
    /** @return \Generator<int, StockData, mixed, void> */
    public function normalize(string $archive, string $timezone): \Generator
    {
        $path = tempnam(sys_get_temp_dir(), 'wb-stock-history-');
        if ($path === false || file_put_contents($path, $archive) === false) {
            throw new WildberriesApiException('historical_report_unreadable', false);
        }

        try {
            yield from $this->normalizeFile($path, $timezone);
        } finally {
            @unlink($path);
        }
    }

    /** @return \Generator<int, StockData, mixed, void> */
    public function normalizeFile(string $path, string $timezone): \Generator
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new WildberriesApiException('historical_report_invalid_zip', false);
        }

        try {
            $entry = $this->csvEntry($zip);
            $stat = $zip->statName($entry);
            $maxBytes = (int) config('sellerscope.wildberries.historical_report_max_bytes', 100_000_000);
            if (! is_array($stat) || $stat['size'] > $maxBytes) {
                throw new WildberriesApiException('historical_report_too_large', false);
            }
        } finally {
            $zip->close();
        }

        $stream = fopen("zip://{$path}#{$entry}", 'rb');
        if ($stream === false) {
            throw new WildberriesApiException('historical_report_unreadable', false);
        }

        try {
            yield from $this->readCsv($stream, $timezone);
        } finally {
            fclose($stream);
        }
    }

    private function csvEntry(ZipArchive $zip): string
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (is_string($name) && str_ends_with(strtolower($name), '.csv')) {
                return $name;
            }
        }

        throw new WildberriesApiException('historical_report_missing_csv', false);
    }

    /**
     * @param  resource  $stream
     * @return \Generator<int, StockData, mixed, void>
     */
    private function readCsv($stream, string $timezone): \Generator
    {
        $header = fgetcsv($stream, separator: ',', escape: '');
        if (! is_array($header)) {
            throw WildberriesApiException::schemaDrift();
        }

        $header[0] = ltrim((string) ($header[0] ?? ''), "\xEF\xBB\xBF");
        $columns = array_flip($header);
        foreach (['NmID', 'ChrtID', 'OfficeName'] as $required) {
            if (! isset($columns[$required])) {
                throw WildberriesApiException::schemaDrift();
            }
        }

        $dates = [];
        foreach ($header as $index => $name) {
            if (is_string($name) && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $name) === 1) {
                $dates[$index] = $name;
            }
        }
        if ($dates === []) {
            throw WildberriesApiException::schemaDrift();
        }

        while (($row = fgetcsv($stream, separator: ',', escape: '')) !== false) {
            if ($row === [null]) {
                continue;
            }

            $nmId = $this->requiredCell($row, (int) $columns['NmID']);
            $variantId = $this->requiredCell($row, (int) $columns['ChrtID']);
            $officeName = $this->requiredCell($row, (int) $columns['OfficeName']);

            foreach ($dates as $index => $date) {
                $quantity = $row[$index] ?? null;
                if ($quantity === null || $quantity === '') {
                    continue;
                }

                if (preg_match('/^\d+$/', $quantity) !== 1) {
                    throw WildberriesApiException::schemaDrift();
                }

                yield new StockData(
                    $nmId,
                    'history:'.hash('sha256', mb_strtolower($officeName)),
                    $officeName,
                    mb_strtolower($officeName) === 'маркетплейс' ? 'marketplace' : 'wb',
                    null,
                    $variantId,
                    $this->snapshotAt($date, $timezone),
                    (int) $quantity,
                );
            }
        }
    }

    /** @param list<string|null> $row */
    private function requiredCell(array $row, int $index): string
    {
        $value = $row[$index] ?? null;
        if (! is_string($value) || $value === '') {
            throw WildberriesApiException::schemaDrift();
        }

        return $value;
    }

    private function snapshotAt(string $date, string $timezone): CarbonImmutable
    {
        try {
            $snapshot = CarbonImmutable::createFromFormat('d.m.Y H:i:s', "{$date} 23:59:59", $timezone);
            if ($snapshot === null) {
                throw new \RuntimeException('Invalid historical snapshot date.');
            }

            return $snapshot->utc();
        } catch (Throwable $exception) {
            throw new WildberriesApiException('schema_drift', false, previous: $exception);
        }
    }
}
