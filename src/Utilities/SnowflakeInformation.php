<?php

namespace Dcp\SnowflakeClient\Utilities;

use Dcp\SnowflakeClient\SnowflakeApiClient;

class SnowflakeInformation
{
    private SnowflakeApiClient $client;

    public function __construct(SnowflakeApiClient $client)
    {
        $this->client = $client;
    }
    
    /**
     * Get the current role of the user.
     *
     * @return string
     * @throws \Exception
     */
    public function getCurrentRole(): string
    {
        return $this->client->raw("SELECT CURRENT_ROLE();", 'data.0.0');
    }

    /**
     * List all available warehouses.
     *
     * @return array
     * @throws \Exception
     */
    public function listWarehouses(): array
    {
        return array_map(function ($warehouse) {
            return $warehouse['name'] ?? null;
        }, $this->client->getRequest("/api/v2/warehouses"));
    }

    /**
     * List all available databases in the current warehouse.
     *
     * @return array
     * @throws \Exception
     */
    public function listDatabases(): array
    {
        return array_map(function ($database) {
            return $database['name'] ?? null;
        }, $this->client->getRequest("/api/v2/databases"));
    }

    /**
     * Get the schemas of a specific database.
     *
     * @param string $databaseName
     * @return array
     * @throws \Exception
     */
    public function listSchemas(string $databaseName): array
    {
        return array_map(function ($schema) {
            return $schema['name'] ?? null;
        }, $this->client->getRequest("/api/v2/databases/" . $databaseName . "/schemas"));
    }

    /**
     * List all tables in the current schema.
     * 
     * @param string $databaseName
     * @param string $schemaName
     * @return array
     * @throws \Exception
     */
    public function listTables(string $databaseName, string $schemaName): array
    {
        return array_map(function ($table) {
            return $table['name'] ?? null;
        }, $this->client->getRequest("/api/v2/databases/" . $databaseName . "/schemas/" . $schemaName . "/tables"));
    }

    /**
     * List all views in the current schema.
     * 
     * @param string $databaseName
     * @param string $schemaName
     * @return array
     * @throws \Exception
     */
    public function listViews(string $databaseName, string $schemaName): array
    {
        return array_map(function ($table) {
            return $table['name'] ?? null;
        }, $this->client->getRequest("/api/v2/databases/" . $databaseName . "/schemas/" . $schemaName . "/views"));
    }

    /**
     * List warehouse names using SQL and the selected role.
     */
    public function showWarehouses(): array
    {
        return $this->getShowNames('SHOW WAREHOUSES');
    }

    /**
     * List database names using SQL and the selected role.
     */
    public function showDatabases(): array
    {
        return $this->getShowNames('SHOW DATABASES');
    }

    /**
     * List schema names using SQL and the selected role.
     */
    public function showSchemas(string $databaseName): array
    {
        return $this->getShowNames('SHOW SCHEMAS IN DATABASE ' . $this->quoteIdentifier($databaseName));
    }

    /**
     * List table names using SQL and the selected role.
     */
    public function showTables(string $databaseName, string $schemaName): array
    {
        return $this->getShowNames(
            'SHOW TABLES IN SCHEMA ' . $this->quoteIdentifier($databaseName) . '.' . $this->quoteIdentifier($schemaName)
        );
    }

    /**
     * List view names using SQL and the selected role.
     */
    public function showViews(string $databaseName, string $schemaName): array
    {
        return $this->getShowNames(
            'SHOW VIEWS IN SCHEMA ' . $this->quoteIdentifier($databaseName) . '.' . $this->quoteIdentifier($schemaName)
        );
    }

    private function getShowNames(string $statement): array
    {
        $result = $this->client->raw($statement);
        $rows = $result['data'] ?? [];
        if (empty($rows)) {
            return [];
        }

        $nameIndex = array_search('name', array_column($result['resultSetMetaData']['rowType'] ?? [], 'name'), true);
        if ($nameIndex === false) {
            throw new \RuntimeException('SHOW result metadata does not contain a name column.');
        }

        return array_map(fn($row) => $row[$nameIndex] ?? null, $rows);
    }

    /**
     * List all views in the current schema.
     * 
     * @param string $databaseName
     * @param string $schemaName
     * @return array
     * @throws \Exception
     */
    public function getTableColumns(string $tableName, ?string $databaseName, ?string $schemaName): array
    {
        $this->client->database($databaseName ?? $this->client->getDatabase());
        $this->client->schema($schemaName ?? $this->client->getSchema());

        $tableInformation = $this->client->raw(
            'DESCRIBE TABLE '
                . $this->quoteIdentifier($databaseName)
                . '.' . $this->quoteIdentifier($schemaName)
                . '.' . $this->quoteIdentifier($tableName)
                . ' TYPE = COLUMNS'
        );

        $tableColumnInfo = $tableInformation['resultSetMetaData']['rowType'] ?? [];
        $tableColumnInfo = array_map(fn($column) => $column['name'], $tableColumnInfo);

        return array_map(function ($row) use ($tableColumnInfo) {
            return array_combine($tableColumnInfo, $row);
        }, $tableInformation['data'] ?? []);
    }

    /**
     * Quote an identifier (e.g., table or column name) for use in SQL queries.
     *
     * @param string $identifier
     * @return string
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}