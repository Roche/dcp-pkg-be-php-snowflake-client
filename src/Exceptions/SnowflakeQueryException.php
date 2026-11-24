<?php

namespace Dcp\SnowflakeClient\Exceptions;

use Exception;
use Throwable;

class SnowflakeQueryException extends Exception
{
    protected ?string $snowflakeCode;
    protected ?string $sqlState;
    protected ?string $statement;

    public function __construct(
        string $message,
        ?string $snowflakeCode = null,
        ?string $sqlState = null,
        ?string $statement = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->snowflakeCode = $snowflakeCode;
        $this->sqlState = $sqlState;
        $this->statement = $statement;
    }

    public static function fromResponse(string|array $response, array $body, ?Throwable $previous = null): self
    {
        $data = is_array($response) ? $response : json_decode($response, true);

        if (is_array($data) && isset($data['message'])) {
            $error = (string) $data['message'];
        } else {
            $error = is_string($response) ? $response : (string) json_encode($response);
            $data = [];
        }

        $error = trim(preg_replace('/\s+/', ' ', $error));
        $code = isset($data['code']) ? (string) $data['code'] : null;
        $location = implode('.', array_filter([$body['database'] ?? null, $body['schema'] ?? null]));

        $message = 'Snowflake query failed'
            . ($location !== '' ? " on {$location}" : '')
            . ': ' . $error
            . ($code !== null ? " (code {$code})" : '');

        return new self($message, $code, $data['sqlState'] ?? null, $body['statement'] ?? null, $previous);
    }

    public function getSnowflakeCode(): ?string
    {
        return $this->snowflakeCode;
    }

    public function getSqlState(): ?string
    {
        return $this->sqlState;
    }

    public function getStatement(): ?string
    {
        return $this->statement;
    }
}
