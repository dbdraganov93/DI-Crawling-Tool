<?php

namespace App\Service;

use App\Entity\IprotoToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;

class IprotoTokenService
{
    private string $host = 'https://og-prod.eu.auth0.com/oauth/token';
    private string $clientId = 'tBGVOOfPO15oQ1b3fHyCL97fuE98koHm';
    private string $clientSecret = 'gYtD81raVXBO9QgC95gfI3ZeAimloZt5HesjNa4r_JBcXixf1maZ4utONCr6ZPBD';

    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function createToken(): void
    {
        $client = HttpClient::create();

        try {
            $response = $client->request('POST', $this->host, [
                'json' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'audience' => 'backend',
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['access_token'], $data['expires_in'], $data['token_type'], $data['scope'])) {
                throw new \RuntimeException('Invalid token response');
            }

            $token = $this->em->getRepository(IprotoToken::class)->find(1);

            if (!$token) {
                $token = new IprotoToken();
                $this->em->persist($token);
            }
            $token->setToken($data['access_token']);
            $token->setTokenType($data['token_type']);
            $token->setScope($data['scope']);
            $token->setExpiresIn((string)$data['expires_in']);
            $token->setCreatedAt(new \DateTimeImmutable());

            $this->em->flush();
        } catch (\Exception $e) {
            throw new \RuntimeException('Error creating token: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getTokenEntity(): ?IprotoToken
    {
        return $this->em->getRepository(IprotoToken::class)->find(1);
    }

    public function getValidToken(): string
    {
        $token = $this->getTokenEntity();

        if (!$token || $this->isExpired($token)) {
            $this->createToken();
            $token = $this->getTokenEntity();
        }

        return $token?->getToken() ?? throw new \RuntimeException('Unable to obtain a valid iProto token');
    }

    private function isExpired(IprotoToken $token): bool
    {
        $createdAt = $token->getCreatedAt();
        $expiresInSeconds = (int)$token->getExpiresIn();

        return $createdAt->getTimestamp() + $expiresInSeconds <= time();
    }
}
