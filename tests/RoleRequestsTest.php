<?php

use Dcp\SnowflakeClient\Services\TokenService;
use Dcp\SnowflakeClient\SnowflakeApiClient;
use Dcp\SnowflakeClient\SnowflakeConnectionConfig;
use Dcp\SnowflakeClient\Utilities\SnowflakeInformation;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

require dirname(__DIR__) . '/vendor/autoload.php';

function checkRoleRequest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function createRoleTestClient(array $responses, array &$history): SnowflakeApiClient
{
    $handler = HandlerStack::create(new MockHandler($responses));
    $handler->push(Middleware::history($history));

    $reflection = new ReflectionClass(SnowflakeApiClient::class);
    $client = $reflection->newInstanceWithoutConstructor();
    $properties = [
        'client' => new Client(['handler' => $handler]),
        'config' => new SnowflakeConnectionConfig([
            'account' => 'test-account',
            'region' => 'test-region',
            'domain' => 'example.invalid',
            'scope' => 'test-scope',
            'username' => 'test-user',
            'password' => 'unused-test-password',
            'oauth_url' => 'https://example.invalid/oauth',
            'client_id' => 'unused-test-client',
            'client_secret' => 'unused-test-secret',
        ]),
        'tokenService' => new class extends TokenService {
            public function __construct()
            {
            }

            public function getOAuthToken()
            {
                return 'offline-test-token';
            }
        },
    ];

    foreach ($properties as $name => $value) {
        $reflection->getProperty($name)->setValue($client, $value);
    }

    return $client;
}

$cases = [
    ['listWarehouses', [], '/api/v2/warehouses', 'SHOW WAREHOUSES', 'showWarehouses'],
    ['listDatabases', [], '/api/v2/databases', 'SHOW DATABASES', 'showDatabases'],
    ['listSchemas', ['DB'], '/api/v2/databases/DB/schemas', 'SHOW SCHEMAS IN DATABASE "DB"', 'showSchemas'],
    ['listTables', ['DB', 'SCHEMA'], '/api/v2/databases/DB/schemas/SCHEMA/tables', 'SHOW TABLES IN SCHEMA "DB"."SCHEMA"', 'showTables'],
    ['listViews', ['DB', 'SCHEMA'], '/api/v2/databases/DB/schemas/SCHEMA/views', 'SHOW VIEWS IN SCHEMA "DB"."SCHEMA"', 'showViews'],
];

try {
    $testCount = 0;
    foreach ([null, 'Mixed Case Role', ''] as $role) {
        foreach ($cases as [$method, $arguments, $endpoint, $statement, $showMethod]) {
            $nextPage = '/api/v2/results/test-result?page=1';
            $history = [];
            $client = createRoleTestClient([
                new Response(200, ['Link' => '<' . $nextPage . '>; rel="next"'], json_encode(['data' => [['name' => 'FIRST']]])),
                new Response(200, [], json_encode(['data' => [['name' => 'SECOND']]])),
            ], $history);
            if ($role !== null) {
                checkRoleRequest($client->setRole($role) === $client, 'setRole must remain fluent.');
            }

            $information = new SnowflakeInformation($client);
            checkRoleRequest($information->$method(...$arguments) === ['FIRST', 'SECOND'], 'Metadata pages must be merged.');
            checkRoleRequest(count($history) === 2, 'Metadata must fetch both pages.');
            foreach ($history as $page => $transaction) {
                $request = $transaction['request'];
                checkRoleRequest($request->getMethod() === 'GET', 'Metadata must remain a GET request.');
                checkRoleRequest((string) $request->getUri() === 'https://test-account.test-region.example.invalid' . ($page === 0 ? $endpoint : $nextPage), 'Metadata URI must remain unchanged.');
                $headers = array_map('strtolower', array_keys($request->getHeaders()));
                sort($headers);
                checkRoleRequest($headers === ['accept', 'authorization', 'content-type', 'host', 'user-agent'], 'Metadata must not add unsupported headers.');
                checkRoleRequest($request->getHeaderLine('Authorization') === 'Bearer offline-test-token', 'Metadata authentication must remain unchanged.');
                checkRoleRequest((string) $request->getBody() === '', 'Metadata must not add a role body.');
            }
            $testCount++;

            $nextPartition = '/api/v2/statements/test-handle?partition=1';
            $history = [];
            $client = createRoleTestClient([
                new Response(200, ['Link' => '<' . $nextPartition . '>; rel="next"'], json_encode([
                    'statementHandle' => 'test-handle',
                    'resultSetMetaData' => ['rowType' => [
                        ['name' => 'comment', 'type' => 'text'],
                        ['name' => 'name', 'type' => 'text'],
                    ]],
                    'data' => [['First comment', 'FIRST']],
                ])),
                new Response(200, [], json_encode(['data' => [['Second comment', 'SECOND']]])),
            ], $history);
            if ($role !== null) {
                $client->setRole($role);
            }

            $information = new SnowflakeInformation($client);
            checkRoleRequest($information->$showMethod(...$arguments) === ['FIRST', 'SECOND'], 'SHOW must extract names by metadata and merge partitions.');
            checkRoleRequest(count($history) === 2, 'SHOW must fetch both partitions.');
            $request = $history[0]['request'];
            checkRoleRequest($request->getMethod() === 'POST', 'SHOW must use the SQL API.');
            checkRoleRequest($request->getUri()->getPath() === '/api/v2/statements', 'SHOW must use the statements endpoint.');
            $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $expectedBody = ['statement' => $statement];
            if ($role !== null && $role !== '') {
                $expectedBody['role'] = $role;
            }
            checkRoleRequest($body === $expectedBody, 'SQL role must be preserved exactly or omitted when unset/empty.');
            $partitionRequest = $history[1]['request'];
            checkRoleRequest($partitionRequest->getMethod() === 'GET', 'SQL partitions must use GET.');
            checkRoleRequest((string) $partitionRequest->getUri() === 'https://test-account.test-region.example.invalid' . $nextPartition, 'Partition URI must remain unchanged.');
            checkRoleRequest(!$partitionRequest->hasHeader('X-Snowflake-Role'), 'Partitions must not add an unsupported role header.');
            $testCount++;
        }
    }

    foreach ($cases as [$method, $arguments, $endpoint, $statement, $showMethod]) {
        $history = [];
        $client = createRoleTestClient([
            new Response(200, [], json_encode([
                'statementHandle' => 'test-handle',
                'resultSetMetaData' => ['rowType' => []],
                'data' => [],
            ])),
        ], $history);
        $information = new SnowflakeInformation($client);
        checkRoleRequest($information->$showMethod(...$arguments) === [], 'Empty SHOW results must return an empty array.');
        checkRoleRequest(count($history) === 1, 'Empty SHOW results must not request another partition.');
        $testCount++;
    }

    $quotedCases = [
        ['showSchemas', ['Mixed "DB".Name'], 'SHOW SCHEMAS IN DATABASE "Mixed ""DB"".Name"'],
        ['showTables', ['Mixed "DB".Name', 'Schema";--'], 'SHOW TABLES IN SCHEMA "Mixed ""DB"".Name"."Schema"";--"'],
        ['showViews', ['Mixed "DB".Name', 'Schema";--'], 'SHOW VIEWS IN SCHEMA "Mixed ""DB"".Name"."Schema"";--"'],
    ];
    foreach ($quotedCases as [$showMethod, $arguments, $statement]) {
        $history = [];
        $client = createRoleTestClient([
            new Response(200, [], json_encode([
                'statementHandle' => 'test-handle',
                'resultSetMetaData' => ['rowType' => [['name' => 'name', 'type' => 'text']]],
                'data' => [['Mixed Case Name']],
            ])),
        ], $history);
        $client->setRole('Mixed Case Role');
        $information = new SnowflakeInformation($client);
        checkRoleRequest($information->$showMethod(...$arguments) === ['Mixed Case Name'], 'SHOW must preserve name casing.');
        $body = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        checkRoleRequest($body === ['statement' => $statement, 'role' => 'Mixed Case Role'], 'SHOW must quote each identifier and escape embedded quotes.');
        $testCount++;
    }

    $history = [];
    $client = createRoleTestClient([
        new Response(200, [], json_encode([
            'statementHandle' => 'test-handle',
            'resultSetMetaData' => ['rowType' => [['name' => 'comment', 'type' => 'text']]],
            'data' => [['Not an object name']],
        ])),
    ], $history);
    $information = new SnowflakeInformation($client);
    $missingNameRejected = false;
    try {
        $information->showDatabases();
    } catch (RuntimeException $exception) {
        $missingNameRejected = $exception->getMessage() === 'SHOW result metadata does not contain a name column.';
    }
    checkRoleRequest($missingNameRejected, 'Nonempty SHOW results without a name column must fail with a fixed error.');
    $testCount++;

    echo "Passed {$testCount} offline role request tests.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Role request tests failed; request headers, bodies, and exception details are suppressed.\n");
    exit(1);
}