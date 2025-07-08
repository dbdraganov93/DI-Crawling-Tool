<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\IprotoToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IprotoTokenService
{
    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private string $iprotoAuthHost,
        private string $iprotoClientId,
        private string $iprotoClientSecret
    ) {
    }

    public function createToken(): void
    {
        try {
            $response = $this->httpClient->request('POST', $this->iprotoAuthHost, [
                'json' => [
                    'client_id' => $this->iprotoClientId,
                    'client_secret' => $this->iprotoClientSecret,
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
