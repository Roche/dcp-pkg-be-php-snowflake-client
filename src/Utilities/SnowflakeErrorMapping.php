<?php

namespace Dcp\SnowflakeClient\Utilities;

class SnowflakeErrorMapping
{
    /**
     * Map error code to a human-readable message.
     */
    public static function getErrorMessageFromCode(int $code): string
    {
        $errorMessages = self::getErrorCodeMapping();

        return $errorMessages[$code] ?? 'Unknown error code';
    }

    /**
     * Map SQL state to a human-readable message.
     */
    public static function getErrorMessageFromSqlState(?string $sqlState): string
    {
        $sqlStateMessages = self::getSqlStateMapping();

        return $sqlStateMessages[$sqlState] ?? 'Unknown SQL state';
    }

    /**
     * Get the mapping for error codes to human-readable messages.
     */
    private static function getErrorCodeMapping(): array
    {
        return [
            1001 => 'Query executed successfully',
            1002 => 'Database not found',
            1003 => 'Table not found',
            1004 => 'Invalid column name',
            1005 => 'Invalid query syntax',
            1006 => 'Permission denied',
            // Add more error codes here as needed
        ];
    }

    /**
     * Get the mapping for SQL states to human-readable messages.
     */
    private static function getSqlStateMapping(): array
    {
        return [
            '00000' => 'Successful completion',
            '22001' => 'String data, right truncation',
            '22003' => 'Numeric value out of range',
            '22007' => 'Invalid datetime format',
            '08003' => 'Connection does not exist',
            '28000' => 'Invalid authorization specification',
            // Add more SQL states here as needed
        ];
    }
}
