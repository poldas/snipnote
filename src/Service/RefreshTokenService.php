<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class RefreshTokenService
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%env(int:REFRESH_TOKEN_TTL_SECONDS)%')]
        private readonly int $refreshTokenTtlSeconds,
    ) {
    }

    public function issue(User $user): string
    {
        $token = $this->generateToken();
        $expiresAt = (new \DateTimeImmutable())->modify(\sprintf('+%d seconds', $this->refreshTokenTtlSeconds));

        $refreshToken = new RefreshToken($user, $token, $expiresAt);

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        return $token;
    }

    /**
     * @return array{user: User, refreshToken: string}
     */
    public function rotate(string $refreshToken): array
    {
        $existing = $this->refreshTokenRepository->findActiveByToken($refreshToken);
        if (null === $existing) {
            throw new AuthenticationException('Invalid refresh token');
        }

        $existing->revoke();

        $newToken = $this->issue($existing->getUser());

        $this->entityManager->flush();

        return [
            'user' => $existing->getUser(),
            'refreshToken' => $newToken,
        ];
    }

    public function revoke(string $refreshToken): void
    {
        $existing = $this->refreshTokenRepository->findOneBy(['token' => $refreshToken]);
        if (null === $existing || $existing->isRevoked()) {
            return;
        }

        $existing->revoke();
        $this->entityManager->flush();
    }

    private function generateToken(): string
    {
        return mb_rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }
}
