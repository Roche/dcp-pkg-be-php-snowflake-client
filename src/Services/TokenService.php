<?php

namespace Dcp\SnowflakeClient\Services;

use GuzzleHttp\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Dcp\SnowflakeClient\SnowflakeConnectionConfig;

class TokenService
{
    private Client $client;

    private string $scope;
    private string $username;
    private string $password;
    private string $oauthUrl;
    private string $clientId;
    private string $clientSecret;

    public function __construct(SnowflakeConnectionConfig $config)
    {
        $this->scope = $config->scope;
        $this->username = $config->username;
        $this->password = $config->password;
        $this->oauthUrl = $config->oauthUrl;
        $this->clientId = $config->clientId;
        $this->clientSecret = $config->clientSecret;

        if ($this->config->verifySsl ?? true) {
            $this->client = new Client();
        } else {
            $this->client = new Client(['verify' => false]);
        }
    }

    public function getOAuthToken()
    {
        if (!$this->isTokenValid()) {
            $this->fetchOAuthToken();
        }

        return $this->getToken();
    }

    private function fetchOAuthToken()
    {
        try {
            $response = $this->client->post($this->oauthUrl, [
                'form_params' => [
                    'grant_type' => 'password',
                    'scope' => $this->scope,
                    'username' => $this->username,
                    'password' => $this->password
                ],
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->clientId . ":" . $this->clientSecret)
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['access_token']) && isset($data['refresh_token']) && isset($data['expires_in'])) {
                Cache::put('snowflake_oauth_' . md5($this->scope . '_' . $this->username), $data['access_token'], $data['expires_in'] - 60);
                Cache::put('snowflake_refresh_' . md5($this->scope . '_' . $this->username), $data['refresh_token']);
                Cache::put('snowflake_oauth_' . md5($this->scope . '_' . $this->username) . '_expires_at', Carbon::now()->addSeconds($data['expires_in']));
            } else {
                throw new \Exception("Failed to retrieve OAuth token.");
            }
        } catch (\Exception $e) {
            throw new \Exception('OAuth token request failed: ' . $e->getMessage());
        }
    }

    public function refreshOAuthToken()
    {
        $refreshToken = Cache::get('snowflake_refresh_token_' . $this->scope);
        if (!$refreshToken) {
            throw new \Exception('No refresh token found to refresh OAuth token.');
        }

        try {
            $response = $this->client->post($this->oauthUrl, [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken
                ],
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->clientId . ":" . $this->clientSecret)
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['access_token']) && isset($data['expires_in'])) {
                Cache::put('snowflake_oauth_' . md5($this->scope . '_' . $this->username), $data['access_token'], $data['expires_in'] - 60);
                Cache::put('snowflake_refresh_' . md5($this->scope . '_' . $this->username), $data['refresh_token']);
                Cache::put('snowflake_oauth_' . md5($this->scope . '_' . $this->username) . '_expires_at', Carbon::now()->addSeconds($data['expires_in']));
            } else {
                throw new \Exception("Failed to refresh OAuth token.");
            }
        } catch (\Exception $e) {
            throw new \Exception('OAuth token refresh failed: ' . $e->getMessage());
        }
    }

    private function isTokenValid()
    {
        $expiresAt = Cache::get('snowflake_oauth_' . md5($this->scope . '_' . $this->username) . '_expires_at');
        return $expiresAt && Carbon::now()->lessThan($expiresAt);
    }

    private function getToken()
    {
        return Cache::get('snowflake_oauth_' . md5($this->scope . '_' . $this->username));
    }
}
