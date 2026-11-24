<?php

namespace Dcp\SnowflakeClient\QueryBuilder;

use Dcp\SnowflakeClient\Helpers\Identifier;

class ConditionBuilder
{
    protected $whereClauses = [];
    protected $bindings = [];
    protected int $paramCounter = 0;

    public function addCondition(string $conditionType, string $column, mixed $operatorOrValue, mixed $value = null): void
    {
        $column = Identifier::quoteNames($column);
        
        // Resolve operator and value
        if ($operatorOrValue === 'IN' && is_array($value)) {
            // Handle IN clause, where the value is an array
            $paramKeys = [];
            foreach ($value as $inValue) {
                $paramKey = $this->generateParamKey();
                $paramKeys[] = $paramKey;
                $this->bindings[$paramKey] = $this->castBinding($inValue);
            }

            $clause = "{$column} IN (:" . implode(', :', $paramKeys) . ")";
        } else {
            [$operator, $finalValue] = $this->resolveOperatorAndValue($operatorOrValue, $value);

            $paramKey = $this->generateParamKey();
            $clause = "{$column} {$operator} :{$paramKey}";

            $this->bindings[$paramKey] = $this->castBinding($finalValue);
        }

        // Store conditions
        $this->whereClauses[] = [
            'type' => $conditionType,
            'clause' => $clause            
        ];
    }

    public function startGroup(string $boolean = 'AND'): void
    {
        $this->whereClauses[] = ['type' => $boolean, 'clause' => '('];
    }

    public function endGroup(): void
    {
        $this->whereClauses[] = ['type' => '', 'clause' => ')'];
    }

    public function addNestedGroup(string $boolean, callable $callback): void
    {
        $this->startGroup($boolean);
        $callback($this);
        $this->endGroup();
    }

    private function generateParamKey(string $prefix = 'param'): string
    {
        return $prefix . (++$this->paramCounter);
    }

    public function castBinding(mixed $value): array
    {
        if (is_null($value)) {
            return ['type' => 'null', 'value' => null];
        }

        if (is_array($value) || is_resource($value)) {
            return ['type' => 'BINARY', 'value' => $value];
        }

        if ($value instanceof \DateTimeInterface) {
            return $this->castDateTime($value);
        }

        return $this->castPrimitive($value);
    }

    private function castPrimitive(mixed $value): array
    {
        switch (gettype($value)) {
            case 'boolean':
                return ['type' => 'BOOLEAN', 'value' => $value];
            case 'integer':
                return ['type' => 'FIXED', 'value' => $value];
            case 'double': // float values in PHP are of type "double"
                return ['type' => 'REAL', 'value' => $value];
            case 'string':
                return ['type' => 'TEXT', 'value' => $value];
            default:
                throw new \InvalidArgumentException("Unsupported parameter type: " . gettype($value));
        }
    }

    private function castDateTime(\DateTimeInterface $value): array
    {
        return ['type' => 'TIMESTAMP_NTZ', 'value' => $value->format('Y-m-d H:i:s')];
    }

    protected function resolveOperatorAndValue(mixed $operatorOrValue, mixed $value = null): array
    {
        if ($value === null) {
            // Assume "=" as the operator when value is not provided
            return ['=', $operatorOrValue];
        }
        // Use provided operator and value
        return [$operatorOrValue, $value];
    }

    public function getConditions(): string
    {
        $conditionsString = '';

        $newGroup = false;
        foreach ($this->whereClauses as $index => $whereClause) {
            $conditionsString .= $index ? ' ' : '';
            $conditionsString .= !$newGroup ? $whereClause['type'] . ' ' : '';
            $conditionsString .= $whereClause['clause'];

            $newGroup = $whereClause['clause'] === '(';
        }

        return $conditionsString;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function hasConditions(): bool
    {
        return count($this->whereClauses) > 0;
    }

    public function resetConditions(): void
    {
        $this->whereClauses = [];
        $this->bindings = [];
        $this->paramCounter = 0;
    }
}
