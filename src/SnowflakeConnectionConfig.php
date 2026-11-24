<?php

namespace Dcp\SnowflakeClient;

class SnowflakeConnectionConfig
{
    public ?string $account;
    public ?string $region;
    public ?string $domain;
    public string $scope;
    public string $username;
    public string $password;
    public string $oauthUrl;
    public string $clientId;
    public string $clientSecret;

    public bool $verifySsl = true;
    public ?object $proxy = null;

    public function __construct(array $config)
    {
        $this->account = $config['account'] ?? null;
        $this->region = $config['region'] ?? null;
        $this->domain = $config['domain'] ?? null;
        $this->scope = $config['scope'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->oauthUrl = $config['oauth_url'];
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];

        $this->verifySsl = $config['verify_ssl'] ?? true;
        $this->proxy = $this->parseProxy($config['proxy'] ?? null);
    }

    private function parseProxy($proxyConfig): ?object
    {
        if (is_array($proxyConfig)) {
            $requiredProxyKeys = ['client_id', 'client_secret', 'url'];
            $hasAllKeys = !array_diff_key(array_flip($requiredProxyKeys), $proxyConfig);
            if ($hasAllKeys) {
                return (object) $proxyConfig;
            }
        }
        return null;
    }
}
