<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IprotoService
{
    private string $host;
    private string $clientId;
    private string $clientSecret;
    private string $tempTokenFile;

    public function __construct()
    {
        $this->host = 'https://og-prod.eu.auth0.com/oauth/token';
        $this->clientId = 'tBGVOOfPO15oQ1b3fHyCL97fuE98koHm';
        $this->clientSecret = 'gYtD81raVXBO9QgC95gfI3ZeAimloZt5HesjNa4r_JBcXixf1maZ4utONCr6ZPBD';
        $this->tempTokenFile = '';
    }

    public function createToken(): void
    {
        $timestamp = time();
        $client = HttpClient::create();

        try {
            $response = $client->request('POST', $this->host, [
                'json' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'audience' => 'backend',
                    'grant_type' => 'client_credentials',
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            $responseData = $response->toArray();

            $expiresIn = $responseData['expires_in'] ?? null;
            $accessToken = $responseData['access_token'] ?? null;

            if (!$expiresIn || !$accessToken) {
                throw new HttpException($response->getStatusCode(), 'Invalid response from token server.');
            }

            $content = ($timestamp + $expiresIn) . PHP_EOL . $accessToken;
            var_dump($responseData);
var_dump($content);
        } catch (\Exception $e) {
            throw new \RuntimeException('Error creating token: ' . $e->getMessage(), 0, $e);
        }
    }
}
