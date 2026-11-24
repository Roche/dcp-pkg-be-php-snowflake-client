<?php

namespace Dcp\SnowflakeClient\Helpers;

class Column
{
    /**
     * Backward-compatible wrapper for legacy column quoting calls.
     *
     * @param string|array $column
     *
     * @return string|array
     */
    public static function quoteNames(string|array $column): string|array
    {
        return Identifier::quoteNames($column);
    }
}
