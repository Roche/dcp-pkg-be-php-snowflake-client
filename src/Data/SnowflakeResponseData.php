<?php

namespace Dcp\SnowflakeClient\Data;

use Spatie\LaravelData\Data;
use Illuminate\Support\Collection;

use Dcp\SnowflakeClient\Utilities\SnowflakeErrorMapping;
use Dcp\SnowflakeClient\Utilities\SnowflakeData;
use Dcp\SnowflakeClient\Utilities\SnowflakeMetadata;

class SnowflakeResponseData extends Data
{
    public string $status;
    public ?string $message;
    public ?int $code;
    public SnowflakeData $data;
    public ?string $sqlState;

    public function __construct(
        string $status,
        ?string $message,
        ?int $code,
        SnowflakeData $data,
        ?string $sqlState = null
    ) {
        $this->status = $status;
        $this->message = $message;
        $this->code = $code;
        $this->data = $data;
        $this->sqlState = $sqlState;
    }

    public static function fromArray(array $attributes): self
    {
        $data = new SnowflakeData(
            $attributes['data'],
            new SnowflakeMetadata($attributes['resultSetMetaData']['rowType'] ?? [])
        );
        $sqlState = $attributes['sqlState'] ?? null;

        return new self(
            status: $attributes['status'] ?? 'unknown',
            message: $attributes['message'] ?? null,
            code: $attributes['code'] ?? null,
            data: $data,
            sqlState: $sqlState
        );
    }

    public function getErrorMessageFromCode(): ?string
    {
        return SnowflakeErrorMapping::getErrorMessageFromCode($this->code);
    }

    public function getErrorMessageFromSqlState(): ?string
    {
        return SnowflakeErrorMapping::getErrorMessageFromSqlState($this->sqlState);
    }

    public function getFullErrorMessage(): string
    {
        $codeMessage = $this->getErrorMessageFromCode();
        $sqlStateMessage = $this->getErrorMessageFromSqlState();

        return $codeMessage . ($sqlStateMessage ? ' - ' . $sqlStateMessage : '');
    }

    public function getColumns(): array
    {
        return $this->data->metadata->getColumns();
    }

    public function getColumnByName(string $columnName): ?ColumnMetadata
    {
        return $this->data->metadata->getColumnByName($columnName);
    }

    public function getRows(): Collection
    {
        return $this->data->getRows();
    }

    public function getRowCount(): int
    {
        return $this->data->rowCount();
    }

    public function isEmpty(): bool
    {
        return $this->data->empty();
    }

    public function map(callable $callback): Collection
    {
        return $this->data->map($callback);
    }

    public function getColumnDataByName(string $columnName): Collection
    {
        return $this->data->getColumnDataByName($columnName);
    }

    public function getColumnIndexByName(string $columnName): ?int
    {
        return $this->data->metadata->getColumnIndexByName($columnName);
    }
}
