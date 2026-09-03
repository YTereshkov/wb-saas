<?php

namespace App\Modules\Analytics\Data;

final readonly class PrimarySignalData
{
    /**
     * @param  array<string, int|float|string|null>  $evidence
     */
    public function __construct(
        public string $type,
        public int $priority,
        public string $severity,
        public string $fact,
        public string $risk,
        public string $recommendation,
        public array $evidence,
    ) {}
}
