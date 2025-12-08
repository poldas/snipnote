<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Service\RefreshTokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class RefreshTokenServiceTest extends TestCase
{
    private RefreshTokenRepository $repository;
    private EntityManagerInterface $entityManager;
    private RefreshTokenService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(RefreshTokenRepository::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);

        $this->service = new RefreshTokenService(
            refreshTokenRepository: $this->repository,
            entityManager: $this->entityManager,
            refreshTokenTtlSeconds: 3600,
        );
    }

    public function testRotateRevokesOldAndReturnsNewToken(): void
    {
        $user = new User('user@example.com', 'hash');
        $oldToken = new RefreshToken(
            $user,
            'old-token',
            (new \DateTimeImmutable())->modify('+1 hour')
        );

        $this->repository
            ->method('findActiveByToken')
            ->with('old-token')
            ->willReturn($oldToken);

        $result = $this->service->rotate('old-token');

        self::assertSame($user, $result['user']);
        self::assertNotSame('old-token', $result['refreshToken']);
        self::assertNotEmpty($result['refreshToken']);
        self::assertNotNull($oldToken->getRevokedAt());
    }

    public function testRotateFailsOnInvalidToken(): void
    {
        $this->repository
            ->method('findActiveByToken')
            ->with('invalid')
            ->willReturn(null);

        $this->expectException(AuthenticationException::class);

        $this->service->rotate('invalid');
    }

    public function testRevokeIsIdempotent(): void
    {
        $user = new User('user@example.com', 'hash');
        $token = new RefreshToken(
            $user,
            'token',
            (new \DateTimeImmutable())->modify('+1 hour')
        );

        $this->repository
            ->method('findOneBy')
            ->with(['token' => 'token'])
            ->willReturn($token);

        $this->service->revoke('token');
        $firstRevoked = $token->getRevokedAt();
        $this->service->revoke('token');

        self::assertNotNull($firstRevoked);
        self::assertSame($firstRevoked, $token->getRevokedAt());
    }
}
