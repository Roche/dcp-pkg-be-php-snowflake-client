<?php

namespace Dcp\SnowflakeClient\Utilities;

use Carbon\Carbon;

class SnowflakeDataCasting
{
    /**
     * Cast a single value based on the column type.
     *
     * @param mixed $value
     * @param string $columnType
     * @return mixed
     */
    public static function castValue(mixed $value, string $columnType)
    {
        // Return null are value
        if (is_null($value)) {
            return $value;
        }

        // Apply the appropriate casting logic
        switch ($columnType) {
            case 'timestamp_tz':
            case 'timestamp_ltz':
            case 'timestamp_ntz':
                return self::castTimestamp($value, $columnType);
            case 'varchar':
            case 'text':
                return self::castText($value);
            case 'boolean':
                return self::castBoolean($value);
            case 'fixed':
            case 'real':
                return self::castNumeric($value);
            case 'date':
            case 'time':
                return self::castTemporal($value);
            case 'binary':
                return self::castBinary($value);
            case 'array':
                return self::castArray($value);
            default:
                throw new \InvalidArgumentException("Unsupported binding type: {$columnType}");
        }
    }

    /**
     * Cast timestamp_tz, timestamp_ltz, or timestamp_ntz formats.
     *
     * @param string $value
     * @param string $columnType
     * @return Carbon
     */
    public static function castTimestamp($value, string $columnType)
    {
        // Split timestamp and timezone part (for timestamp_tz)
        if ($columnType === 'timestamp_tz') {
            $parts = explode(' ', $value);
            $timestamp = (float) $parts[0]; // Includes fractional seconds
            $timezoneOffsetMinutes = (int) $parts[1];

            // If timezoneOffset is 1440, set it to UTC (0 offset)
            if ($timezoneOffsetMinutes === 1440 || $timezoneOffsetMinutes === -1440) {
                $timezoneOffsetMinutes = 0;
            }

            // Convert timezone offset from minutes to hours
            $timezoneOffsetHours = $timezoneOffsetMinutes / 60;

            // Extract integer and fractional parts
            $seconds = (int)$timestamp;
            $nanoseconds = (int)(($timestamp - $seconds) * 1_000_000_000); // Convert fractional part to nanoseconds

            // Convert seconds to Carbon
            $carbonDate = Carbon::createFromTimestamp($seconds, $timezoneOffsetHours);

            // Add nanoseconds as microseconds (rounded to the nearest microsecond)
            $microseconds = (int)($nanoseconds / 1_000); // Convert nanoseconds to microseconds
            $carbonDate->setMicrosecond($microseconds);

            return $carbonDate;
        }

        // For other timestamp types (timestamp_ltz and timestamp_ntz)
        return Carbon::parse($value); // Assuming value is in a format supported by Carbon
    }

    /**
     * Cast text (TEXT or VARCHAR).
     *
     * @param mixed $value
     * @return string
     */
    public static function castText($value)
    {
        return $value;
    }

    /**
     * Cast boolean values.
     *
     * @param mixed $value
     * @return string
     */
    public static function castBoolean($value)
    {
        return $value ? true : false;
    }

    /**
     * Cast numeric values (FIXED or REAL).
     *
     * @param mixed $value
     * @return mixed
     */
    public static function castNumeric($value)
    {
        // Check if the value is numeric
        if (is_numeric($value)) {
            // Return as an integer or float depending on the input
            return strpos((string)$value, '.') !== false ? (float)$value : (int)$value;
        }

        // If the value is not numeric, throw an exception
        throw new \InvalidArgumentException("Invalid numeric value: {$value}");
    }

    /**
     * Cast temporal values (DATE or TIME).
     *
     * @param mixed $value
     * @return string
     */
    public static function castTemporal($value)
    {
        return $value; // Format DATE and TIME as strings
    }

    /**
     * Cast binary values.
     *
     * @param mixed $value
     * @return string
     */
    public static function castBinary($value)
    {
        return bin2hex($value); // Convert binary to hexadecimal format
    }

    /**
     * Cast array values.
     *
     * @param mixed $value
     * @return string
     */
    public static function castArray($value): string
    {
        // If already an array, return as is
        if (is_array($value)) {
            return json_encode($value);
        }
        // If it's a JSON string, decode it
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded);
            }
        }
        // Otherwise, throw an exception
        throw new \InvalidArgumentException("Invalid array value: " . var_export($value, true));
    }
}
