<?php

namespace Dcp\SnowflakeClient\Utilities;

use Dcp\SnowflakeClient\Data\ColumnMetadata;

class SnowflakeMetadata
{
    /** @var ColumnMetadata[] */
    public array $columns;

    public function __construct(array $columns)
    {
        $this->columns = array_map(fn($column) => new ColumnMetadata(...$column), $columns);
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getColumnByName(string $columnName): ?ColumnMetadata
    {
        return collect($this->columns)->firstWhere('name', $columnName);
    }

    public function getColumnIndexByName(string $columnName): ?int
    {
        $columnIndex = collect($this->columns)
            ->search(fn($column) => $column->name === $columnName);

        return $columnIndex !== false ? $columnIndex : null;
    }
}
