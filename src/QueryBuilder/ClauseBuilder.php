<?php

namespace Dcp\SnowflakeClient\QueryBuilder;

use Dcp\SnowflakeClient\Helpers\Identifier;

class ClauseBuilder
{
    protected $distinct = false;
    protected $columns = [];
    protected $groupBy = [];
    protected $orderBy = [];

    public function setDistinct(bool $distinct = true): void
    {
        $this->distinct = $distinct;
    }

    public function isDistinct(): bool
    {
        return $this->distinct;
    }

    public function setSelectColumns(array $columns): void
    {
        $this->columns = $columns;
    }

    public function getSelectColumns(): string
    {
        return implode(', ', Identifier::quoteNames($this->columns));
    }

    public function setGroupBy(array $columns): void
    {
        $this->groupBy = Identifier::quoteNames($columns);
    }

    public function getGroupBy(): string
    {
        return $this->groupBy ? 'GROUP BY '. implode(', ', $this->groupBy) : '';
    }

    public function isGrouped(): bool
    {
        return count($this->groupBy) > 0;
    }

    public function addOrderBy(string $column, string $direction = 'ASC'): void
    {
        $this->orderBy[] = Identifier::quoteNames($column) . ' ' . strtoupper($direction);
    }

    public function getOrderBy(): string
    {
        return $this->orderBy ? 'ORDER BY ' . implode(', ', $this->orderBy) : '';
    }

    public function hasOrder(): bool
    {
        return count($this->orderBy) > 0;
    }

    public function getDistinct(): string
    {
        return $this->distinct ? 'DISTINCT ' : '';
    }

    public function resetClauses(): void
    {
        $this->distinct = false;
        $this->columns = [];
        $this->groupBy = [];
        $this->orderBy = [];
    }

    protected function validateGroupBy(): void
    {
        // Iterate through SELECT columns and ensure they are either aggregates or in GROUP BY
        foreach ($this->columns as $column) {
            // Skip aggregate columns
            if ($this->isAggregate($column)) {
                continue;
            }

            // Check if the column is in GROUP BY
            if (!in_array($column, $this->groupBy, true)) {
                throw new \LogicException(
                    "Column '{$column}' must be included in the GROUP BY clause or used in an aggregate function."
                );
            }
        }
    }

    protected function isAggregate(string $column): bool
    {
        $column = Identifier::quoteNames($column);

        // Check for common aggregate functions (e.g., SUM(), AVG(), COUNT())
        $aggregateFunctions = ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'];

        // Check if the column starts with an aggregate function, ignoring case
        foreach ($aggregateFunctions as $function) {
            if (stripos($column, "{$function}(") === 0) {
                return true;
            }
        }

        return false;
    }
}
