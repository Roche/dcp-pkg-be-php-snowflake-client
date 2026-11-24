<?php

namespace Dcp\SnowflakeClient\Utilities;

use Illuminate\Support\Collection;

class SnowflakeData
{
    public Collection $values;
    public SnowflakeMetadata $metadata;

    public function __construct(array|Collection $data, SnowflakeMetadata $metadata)
    {
        $this->values = ($data instanceof Collection) ? $data : collect($data);
        $this->metadata = $metadata;
    }

    public static function fromArray(array|Collection $data, SnowflakeMetadata $metadata): self
    {
        return new self($data, $metadata);
    }

    public function getFirstRow(): ?array
    {
        return $this->data[0] ?? null;
    }

    public function getRows(): Collection
    {
        return $this->values;
    }

    public function rowCount(): int
    {
        return $this->values->count();
    }

    public function empty(): bool
    {
        return $this->rowCount() === 0;
    }

    public function map(callable $callback): Collection
    {
        return $this->getRows()->map($callback);
    }

    public function getColumnDataByName(string $columnName): Collection
    {
        // Find the index of the column in the metadata
        $columnMetadata = $this->metadata->getColumnByName($columnName);

        if (!$columnMetadata) {
            return collect([]); // Return an empty array if the column name doesn't exist
        }

        // Now map the data to the column index (assuming the column order matches)
        $columnIndex = array_search($columnName, array_column($this->metadata->getColumns(), 'name'));

        // If the column is found, return the data for that column, otherwise return an empty array
        if ($columnIndex !== false) {
            return $this->getRows()
                ->map(fn($row) => $row[$columnIndex] ?? null)
                ->values();
        }

        collect([]); // Return an empty array if the column is not found
    }
}
