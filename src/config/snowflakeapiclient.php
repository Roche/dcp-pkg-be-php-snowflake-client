<?php

return [
    // Verify SSL certificate
    'verify_ssl' => env('DCP_SNOWFLAKE_VERIFY_SSL', true),

    // Snowflake configuration
    'account' => env('DCP_SNOWFLAKE_ACCOUNT', 'snowflake-account'),
    'region' => env('DCP_SNOWFLAKE_REGION', 'snowflake-instance-region'),
    'domain' => env('DCP_SNOWFLAKE_DOMAIN', 'snowflakecomputing.com'),

    // Snowflake OAuth provider configuration
    'username' => env('DCP_SNOWFLAKE_USERNAME', 'your-username'),
    'password' => env('DCP_SNOWFLAKE_PASSWORD', 'your-password'),
    'oauth_url' => env('DCP_SNOWFLAKE_OAUTH_URL', 'https://oauth-url'),
    'client_id' => env('DCP_SNOWFLAKE_CLIENT_ID', 'your-client-it'),
    'client_secret' => env('DCP_SNOWFLAKE_CLIENT_SECRET', 'your-client-secret'),
    'scope' => env('DCP_SNOWFLAKE_SCOPE', 'session:role-any'),

    // Proxy configuration
    'proxy' => (
        env('DCP_SNOWFLAKE_PROXY_CLIENT_ID') ||
        env('DCP_SNOWFLAKE_PROXY_CLIENT_SECRET') ||
        env('DCP_SNOWFLAKE_PROXY_URL')
    ) ? [
        'client_id' => env('DCP_SNOWFLAKE_PROXY_CLIENT_ID', 'your-proxy-client-id'),
        'client_secret' => env('DCP_SNOWFLAKE_PROXY_CLIENT_SECRET', 'your-proxy-client-secret'),
        'url' => env('DCP_SNOWFLAKE_PROXY_URL', 'https://proxy-url')
    ] : null
];
