<?php

namespace Dcp\SnowflakeClient\QueryBuilder;

use Dcp\SnowflakeClient\Helpers\Identifier;

class JoinBuilder
{
    protected $joins = [];

    public function addJoin(string $table, string $firstColumn, string $operator, string $secondColumn, string $joinType = 'INNER'): void
    {
        $joinTypes = ['INNER', 'LEFT', 'RIGHT', 'FULL'];
        
        if (!in_array(strtoupper($joinType), $joinTypes)) {
            throw new \InvalidArgumentException("Invalid join type: {$joinType}. Supported types are: " . implode(', ', $joinTypes));
        }

        $table = Identifier::quoteNames($table);
        $firstColumn = Identifier::quoteNames($firstColumn);
        $secondColumn = Identifier::quoteNames($secondColumn);

        $onCondition = "{$firstColumn} {$operator} {$secondColumn}";
        $this->joins[] = strtoupper($joinType) . " JOIN {$table} ON {$onCondition}";
    }

    public function getJoins(): string
    {
        return implode(' ', $this->joins);
    }

    public function hasJoins(): bool
    {
        return count($this->joins) > 0;
    }

    public function resetJoins(): void
    {
        $this->joins = [];
    }
}