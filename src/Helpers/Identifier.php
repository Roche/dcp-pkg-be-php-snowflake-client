<?php

namespace Dcp\SnowflakeClient\Helpers;

class Identifier
{
    /**
     * Quote an identifier, table name, schema-qualified name, or SQL expression.
     *
     * @param string|array $identifier
     *
     * @return string|array
     */
    public static function quoteNames(string|array $identifier): string|array
    {
        if (is_array($identifier)) {
            return array_map([self::class, 'quoteNames'], $identifier);
        }

        if ($identifier === '*') {
            return $identifier;
        }

        $identifier = preg_replace_callback(
            '/(\b(?:MAX|MIN|COUNT|SUM|AVG|CASE|WHEN|THEN|END|DISTINCT)\b\s*\(([^)]+)\))(\s+AS\s+([a-zA-Z_][a-zA-Z0-9_]*))?/i',
            function ($matches) {
                $function = $matches[1];
                $arguments = $matches[2];
                $alias = $matches[4] ?? null;

                $quotedArguments = preg_replace_callback(
                    '/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/',
                    function ($argMatches) {
                        return '"' . str_replace('"', '""', $argMatches[1]) . '"';
                    },
                    $arguments,
                );

                $quotedFunction = str_replace($arguments, $quotedArguments, $function);

                if ($alias) {
                    $quotedAlias = '"' . str_replace('"', '""', $alias) . '"';

                    return $quotedFunction . ' AS ' . $quotedAlias;
                }

                return $quotedFunction;
            },
            $identifier,
        );

        if (str_contains($identifier, '.') && !preg_match('/\b(?:MAX|MIN|COUNT|SUM|AVG|CASE|WHEN|THEN|END|DISTINCT|AS)\b|\(|\)/i', $identifier)) {
            $parts = explode('.', $identifier);

            return implode('.', array_map(function ($part) {
                return '"' . str_replace('"', '""', $part) . '"';
            }, $parts));
        }

        if (!preg_match('/\b(?:MAX|MIN|COUNT|SUM|AVG|CASE|WHEN|THEN|END|DISTINCT|AS)\b|\(|\)/i', $identifier)) {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }

        return $identifier;
    }
}
