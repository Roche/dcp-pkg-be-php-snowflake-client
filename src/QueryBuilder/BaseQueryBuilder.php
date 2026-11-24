<?php

namespace Dcp\SnowflakeClient\QueryBuilder;

use Dcp\SnowflakeClient\Helpers\Identifier;
use Dcp\SnowflakeClient\QueryBuilder\ConditionBuilder;
use Dcp\SnowflakeClient\Exceptions\InvalidQueryParameter;

class BaseQueryBuilder
{
    protected ConditionBuilder $conditionBuilder;
    protected JoinBuilder $joinBuilder;
    protected ClauseBuilder $clauseBuilder;

    protected string $sql;
    protected ?int $limit;
    protected ?string $having;
    protected ?int $offset;
    protected array $select;
    protected ?string $table = null;
    protected bool $isCount = false;

    public function __construct()
    {
        $this->conditionBuilder = new ConditionBuilder();
        $this->joinBuilder = new JoinBuilder();
        $this->clauseBuilder = new ClauseBuilder();

        $this->sql = '';
        $this->limit = null;
        $this->having = null;
        $this->offset = null;
    }

    public function select(array|string $columns): self
    {
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }

        $columns = array_filter($columns, function ($column) {
            return $column !== null && $column !== '';
        });

        $this->clauseBuilder->setSelectColumns($columns);

        return $this;
    }

    public function distinct(bool $distinct = true): self
    {
        $this->clauseBuilder->setDistinct($distinct);
        return $this;
    }

    public function from(string $table): self
    {
        $this->table = Identifier::quoteNames($table);

        $this->sql = 'SELECT '
            . $this->clauseBuilder->getDistinct()
            . $this->clauseBuilder->getSelectColumns()
            . ' FROM '
            . $this->table;

        return $this;
    }

    protected function getTable(): string
    {
        if (!$this->table) {
            throw new \RuntimeException("Table name is not set. Call from() before count().");
        }
        return $this->table;
    }

    public function where(string|callable $columnOrCallback, mixed $operatorOrValue = null, mixed $value = null): self
    {
        return $this->addCondition('WHERE', $columnOrCallback, $operatorOrValue, $value);
    }

    public function andWhere(string|callable $columnOrCallback, mixed $operatorOrValue = null, mixed $value = null): self
    {
        return $this->addCondition('AND', $columnOrCallback, $operatorOrValue, $value);
    }

    public function orWhere(string|callable $columnOrCallback, mixed $operatorOrValue = null, mixed $value = null): self
    {
        return $this->addCondition('OR', $columnOrCallback, $operatorOrValue, $value);
    }

    private function addCondition(string $type, string|callable $columnOrCallback, mixed $operatorOrValue = null, mixed $value = null): self
    {
        if (is_callable($columnOrCallback)) {
            $this->conditionBuilder->startGroup($type);
            $columnOrCallback($this);
            $this->conditionBuilder->endGroup();
        } else {
            $this->conditionBuilder->addCondition($type, $columnOrCallback, $operatorOrValue, $value);
        }
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            throw new InvalidQueryParameter('whereIn', $values);
        }

        $this->conditionBuilder->addCondition('WHERE', $column, 'IN', $values);
        return $this;
    }

    public function andWhereIn(string $column, array $values): self
    {
        if (empty($values)) {
            throw new InvalidQueryParameter('andWhereIn', $values);
        }

        $this->conditionBuilder->addCondition('AND', $column, 'IN', $values);
        return $this;
    }

    public function orWhereIn(string $column, array $values): self
    {
        if (empty($values)) {
            throw new InvalidQueryParameter('orWhereIn', $values);
        }

        $this->conditionBuilder->addCondition('OR', $column, 'IN', $values);
        return $this;
    }

    public function groupBy(array|string $columns): self
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        $this->clauseBuilder->setGroupBy($columns);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->clauseBuilder->addOrderBy($column, $direction);
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = (int) $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = (int) $offset;
        return $this;
    }

    public function join(string $table, string $firstColumn, string $operator, string $secondColumn, string $joinType = 'INNER'): self
    {
        $this->joinBuilder->addJoin($table, $firstColumn, $operator, $secondColumn, $joinType);
        return $this;
    }

    public function getSql(): string
    {
        $query = $this->sql;

        if ($this->joinBuilder->hasJoins()) {
            $query .= ' ' . $this->joinBuilder->getJoins();
        }

        if ($this->conditionBuilder->hasConditions()) {
            $query .= ' ' . $this->conditionBuilder->getConditions();
        }

        if ($this->clauseBuilder->isGrouped() && !$this->isCount) {
            $query .= ' ' . $this->clauseBuilder->getGroupBy();
        }

        if ($this->having && !$this->isCount) {
            $query .= ' ' . $this->having;
        }

        if ($this->clauseBuilder->hasOrder() && !$this->isCount) {
            $query .= ' ' . $this->clauseBuilder->getOrderBy();
        }

        if ($this->limit && !$this->isCount) {
            $query .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset && !$this->isCount) {
            $query .= ' OFFSET ' . $this->offset;
        }

        return preg_replace('/\s+/', ' ', $query);
    }

    public function getResolvedSql(): string
    {
        $query = $this->getSql();

        if (!$this->getBindings()) {
            return $query;
        }

        foreach ($this->getBindings() as $key => $binding) {
            $value = $binding['value'];
            $type = strtoupper($binding['type']); // Ensure the type is uppercase

            $escapedValue = match ($type) {
                'TEXT' => $value === null ? 'NULL' : "'" . addslashes($value) . "'",
                'BOOLEAN' => $value ? 'TRUE' : 'FALSE',
                'FIXED', 'REAL' => $value, // Use numbers directly
                'DATE', 'TIME', 'TIMESTAMP_TZ', 'TIMESTAMP_LTZ', 'TIMESTAMP_NTZ' => "'" . $value . "'", // Properly format temporal values
                'BINARY' => "X'" . bin2hex($value) . "'", // Binary as hex
                default => throw new \InvalidArgumentException("Unsupported binding type: {$type}"),
            };

            $query = preg_replace("/:$key\b/", $escapedValue, $query);
        }

        return $query;
    }

    public function count(string $column = '*'): self
    {
        $this->isCount = true;
        $this->sql = 'SELECT COUNT(' . Identifier::quoteNames($column) . ') FROM ' . $this->getTable();

        return $this;
    }


    public function getBindings(): ?array
    {
        $this->getSql();
        $bindings = $this->conditionBuilder->getBindings();

        return count($bindings) ? $this->conditionBuilder->getBindings() : null;
    }

    public function reset(): void
    {
        $this->sql = '';
        $this->limit = null;
        $this->having = null;
        $this->offset = null;
        $this->select = [];
        $this->isCount = false;

        $this->joinBuilder->resetJoins();
        $this->conditionBuilder->resetConditions();
        $this->clauseBuilder->resetClauses();
    }
}
