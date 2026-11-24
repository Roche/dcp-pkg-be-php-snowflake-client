<?php

namespace Dcp\SnowflakeClient\Data;

use Spatie\LaravelData\Data;

class ColumnMetadata extends Data
{
    public function __construct(
        public string $name,
        public string $database,
        public string $schema,
        public string $table,
        public ?bool $nullable,
        public ?int $length,
        public string $type,
        public ?int $scale,
        public ?int $precision,
        public ?int $byteLength,
        public ?string $collation
    ) {}
}
