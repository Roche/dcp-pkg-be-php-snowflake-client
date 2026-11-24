<?php

namespace Dcp\SnowflakeClient;

use RuntimeException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Arr;
use Dcp\SnowflakeClient\Services\TokenService;
use Dcp\SnowflakeClient\QueryBuilder\BaseQueryBuilder;
use Dcp\SnowflakeClient\QueryBuilder\ConditionBuilder;
use Dcp\SnowflakeClient\Data\SnowflakeResponseData;
use Dcp\SnowflakeClient\SnowflakeConnectionConfig;
use Dcp\SnowflakeClient\Utilities\SnowflakeDataCasting;
use Dcp\SnowflakeClient\Utilities\SnowflakeInformation;
use Dcp\SnowflakeClient\Exceptions\SnowflakeQueryException;

/**
 * Class SnowflakeApiClient
 *
 * @method self select(array $columns)
 * @method self from(string $table)
 * @method self where(string|callable $columnOrCallback, mixed $operatorOrValue = null, mixed $value = null)
 * @method self whereIn(string $column, array $values)
 * @method self andWhereIn(string $column, array $values)
 * @method self orWhereIn(string $column, array $values)
 * @method self whereNull(string $column)
 * @method self whereNotNull(string $column)
 * @method self andWhere(string|callable $columnOrCallback, mixed $operatorOrValue = null, mixed $value = null)
 * @method self orWhere(string|callable $columnOrCallback, mixed $operatorOrValue = null, mixed $value = null)
 * @method self groupBy(array|string $columns)
 * @method self orderBy(string $column, string $direction = 'ASC')
 * @method self limit(int $limit)
 * @method self having(string $condition)
 * @method self join(string $table, string $firstColumn, string $operator, string $secondColumn, string $joinType = 'INNER')
 * @method self distinct()
 * @method self offset(int $offset)
 * @method string getSql()
 * @method string getResolvedSql()
 * @method ?array getBindings()
 */
class SnowflakeApiClient
{
    const MAGIC_PASS_THROUGH = ['getSql', 'getResolvedSql', 'getBindings'];

    private static ?SnowflakeApiClient $instance = null;
    private static ?string $warehouse = null;
    private static ?string $database = null;
    private static ?string $schema = null;

    private ?string $role = null;
    private Client $client;
    private TokenService $tokenService;
    private BaseQueryBuilder $queryBuilder;
    private SnowflakeConnectionConfig $config;
    private SnowflakeInformation $information;

    public function __construct(?SnowflakeConnectionConfig $config = null)
    {
        if (self::$instance === null) {
            self::$instance = $this;
        }

        $this->config = $config ?? new SnowflakeConnectionConfig(config('snowflakeapiclient'));
        $this->tokenService = new TokenService($this->config);

        $this->queryBuilder = app(BaseQueryBuilder::class);

        if ($this->config->verifySsl ?? true) {
            $this->client = new Client();
        } else {
            $this->client = new Client(['verify' => false]);
        }

        $this->information = new SnowflakeInformation($this);
    }

    /**
     * Get the current warehouse.
     *
     * @return ?string
     */
    public function getWarehouse(): ?string
    {
        return self::$warehouse;
    }

    /**
     * Get the current database.
     *
     * @return ?string
     */
    public function getDatabase(): ?string
    {
        return self::$database;
    }

    /**
     * Get the current schema.
     *
     * @return ?string
     */
    public function getSchema(): ?string
    {
        return self::$schema;
    }

    /**
     * Get the SnowflakeInformation instance.
     *
     * @return SnowflakeInformation
     */
    public function information(): SnowflakeInformation
    {
        return $this->information;
    }

    public function updateConnection(SnowflakeConnectionConfig $config): self
    {
        $this->config = $config;
        $this->tokenService = new TokenService($this->config);

        return $this;
    }

    public function setRole(string $newRole): self
    {
        $this->role = $newRole;

        return $this;
    }

    // Static methods to set warehouse, database, and schema
    public static function warehouse($warehouse): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        self::$warehouse = $warehouse;

        return self::$instance;
    }

    public static function database($database): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        self::$database = $database;

        return self::$instance;
    }

    public static function schema($schema): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        self::$schema = $schema;

        return self::$instance;
    }

    // Magic method to forward calls to the QueryBuilder instance
    public function __call($method, $arguments): self|string|array
    {
        // Forward the method call to the query builder
        if (method_exists($this->queryBuilder, $method)) {
            $result = call_user_func_array([$this->queryBuilder, $method], $arguments);

            if (in_array($method, self::MAGIC_PASS_THROUGH)) {
                return $result;
            }

            return $this;
        }

        throw new \BadMethodCallException("Method {$method} not found in QueryBuilder.");
    }

    protected function prepareBody(string $sql, array $bindings): array
    {
        $body = [
            'statement' => $sql,
        ];

        if (!empty(self::$database)) {
            $body['database'] = self::$database;
        }

        if (!empty(self::$schema)) {
            $body['schema'] = self::$schema;
        }

        if (!empty(self::$warehouse)) {
            $body['warehouse'] = self::$warehouse;
        }

        if (!empty($bindings)) {
            $body['bindings'] = $bindings;
        }

        if (!empty($this->role)) {
            $body['role'] = $this->role;
        }

        return $body;
    }

    // Generic method to send requests to Snowflake API
    private function requestStatement(array $body): array
    {
        $headers = $this->getRequestHeaders();

        try {
            $response = $this->client->post($this->getUrlBase() . "/api/v2/statements", [
                'json'    => $body,
                'headers' => $headers,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['status']) && $responseData['status'] !== 'success') {
                throw SnowflakeQueryException::fromResponse($responseData, $body);
            }

            return [
                'headers' => array_change_key_case($response->getHeaders(), CASE_LOWER),
                'body'    => $responseData,
            ];
        } catch (RequestException $e) {
            throw SnowflakeQueryException::fromResponse(
                $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage(),
                $body,
                $e,
            );
        }
    }

    public function count(): int
    {
        $body = $this->prepareBody(
            $this->queryBuilder->count()->getSql(),
            $this->queryBuilder->getBindings() ?? [],
        );

        $response = $this->requestStatement($body);

        if (!isset($response['body']['data'][0])) {
            throw new \RuntimeException('Failed to retrieve count result.');
        }

        $countValue = reset($response['body']['data'][0]); // Get the first column's value

        // Reset the query builder after execution
        $this->queryBuilder->reset();

        return (int) $countValue;
    }

    public function get(bool $fetchAll = true): SnowflakeResponseData
    {
        $body = $this->prepareBody(
            $this->queryBuilder->getSql(),
            $this->queryBuilder->getBindings() ?? [],
        );

        // Step 1: Execute query and get the first partition
        $initialResponse = $this->requestStatement($body);

        if (!isset($initialResponse['body']['statementHandle'])) {
            throw new \RuntimeException('Failed to retrieve query handle.');
        }

        $allData = $initialResponse['body']['data'] ?? [];

        // Step 2: Retrieve additional partitions
        if ($fetchAll) {
            $nextPartitionLink = $this->getNextPageUrl($initialResponse['headers']['link'][0] ?? null);
            if (!is_null($nextPartitionLink)) {
                $additionalPartitionData = $this->getRequest($nextPartitionLink);
                $allData = array_merge($allData, $additionalPartitionData);
            }
        }

        // Step 3: Cast values based on metadata
        foreach ($allData as &$row) {
            foreach ($row as $column => &$value) {
                $value = SnowflakeDataCasting::castValue(
                    $value,
                    $initialResponse['body']['resultSetMetaData']['rowType'][$column]['type'],
                );
            }
        }

        // Step 4: Cleanup
        $this->queryBuilder->reset();

        return SnowflakeResponseData::fromArray([
            'resultSetMetaData' => $initialResponse['body']['resultSetMetaData'],
            'data'              => $allData,
        ]);
    }

    /**
     * Sends a GET request to the Snowflake API and returns all paginated results.
     *
     * @param string $endpoint
     *
     * @return array
     * @throws \Exception
     */
    public function getRequest(string $endpoint): array
    {
        $url = $endpoint;

        $headers = $this->getRequestHeaders();

        $allData = [];
        do {
            $response = $this->client->get($this->getUrlBase() . $url, [
                'headers' => $headers,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $allData = array_merge($allData, $responseData['data'] ?? []);
            $responseHeaders = array_change_key_case($response->getHeaders(), CASE_LOWER);

            // Parse the Link header to find the "next" page URL
            $url = $this->getNextPageUrl($responseHeaders['link'][0] ?? null);
        } while ($url);

        return $allData;
    }

    /**
     * Extracts the "next" page URL from the Link header.
     *
     * @param string|null $linkHeader
     *
     * @return string|null
     */
    private function getNextPageUrl(?string $linkHeader): ?string
    {
        if (!$linkHeader) {
            return null;
        }

        // Match the "next" URL in the Link header (handles both absolute and relative URLs)
        if (preg_match('/<([^>]+)>;\s*rel="next"/', $linkHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get the base URL for the Snowflake API.
     *
     * @return string
     */
    private function getUrlBase(): string
    {
        if ($this->config->proxy && isset($this->config->proxy->url)) {
            return rtrim($this->config->proxy->url, '/');
        }

        if (!$this->config->account || !$this->config->region || !$this->config->domain) {
            throw new RuntimeException('Snowflake account, region, and domain must be set in the configuration, if no proxy provided.');
        }

        return "https://" . $this->config->account . "." . $this->config->region . "." . $this->config->domain;
    }

    /**
     * Get the request headers for the Snowflake API.
     *
     * @return array
     */
    private function getRequestHeaders(): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->tokenService->getOAuthToken(),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];

        if ($this->config->proxy) {
            $headers['client_id'] = $this->config->proxy->client_id ?? '';
            $headers['client_secret'] = $this->config->proxy->client_secret ?? '';
        }

        return $headers;
    }

    /**
     * Execute a raw SQL query and return the result.
     *
     * @param string $sql
     * @param ?string $segment
     * @param array $bindings
     *
     * @return array | string
     * @throws \Exception
     */
    public function raw(string $sql, ?string $segment = null, array $bindings = []): array
    {
        $conditionBuilder = new ConditionBuilder();

        $bindings = array_map(function ($binding) use ($conditionBuilder) {
            return $conditionBuilder->castBinding($binding);
        }, $bindings);

        // Step 1: Execute the query and get the initial response
        $initialResponse = $this->requestStatement(
            $this->prepareBody($sql, $bindings),
        );

        if (!isset($initialResponse['body']['statementHandle'])) {
            throw new \RuntimeException('Failed to retrieve query handle.');
        }

        $allData = $initialResponse['body']['data'] ?? [];
        $resultSetMetaData = $initialResponse['body']['resultSetMetaData'] ?? [];

        // Step 2: Retrieve additional partitions
        $nextPartitionLink = $this->getNextPageUrl($initialResponse['headers']['link'][0] ?? null);
        if (!is_null($nextPartitionLink)) {
            $additionalPartitionData = $this->getRequest($nextPartitionLink);
            $allData = array_merge($allData, $additionalPartitionData);
        }

        // Step 3: Cast values based on metadata
        foreach ($allData as &$row) {
            foreach ($row as $column => &$value) {
                $value = SnowflakeDataCasting::castValue(
                    $value,
                    $resultSetMetaData['rowType'][$column]['type'],
                );
            }
        }

        // Step 4: Return the requested segment or the full data with metadata
        if ($segment) {
            return [
                'data'              => Arr::get(['data' => $allData], $segment, []),
                'resultSetMetaData' => $resultSetMetaData,
            ];
        }

        return [
            'data'              => $allData,
            'resultSetMetaData' => $resultSetMetaData,
        ];
    }
}
