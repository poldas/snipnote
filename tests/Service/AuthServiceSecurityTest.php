<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\Auth\LoginRequestDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\RefreshTokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class AuthServiceSecurityTest extends TestCase
{
    private EntityManagerInterface&Stub $entityManager;
    private UserRepository&Stub $userRepository;
    private PasswordHasherFactoryInterface&Stub $passwordHasherFactory;
    private PasswordHasherInterface&MockObject $passwordHasher;
    private RefreshTokenService&Stub $refreshTokenService;
    private AuthService $service;

    protected function setUp(): void
    {
        $this->entityManager = self::createStub(EntityManagerInterface::class);
        $this->userRepository = self::createStub(UserRepository::class);
        $this->passwordHasherFactory = self::createStub(PasswordHasherFactoryInterface::class);
        $this->passwordHasher = self::createMock(PasswordHasherInterface::class);
        $this->refreshTokenService = self::createStub(RefreshTokenService::class);

        $this->passwordHasherFactory
            ->method('getPasswordHasher')
            ->with(User::class)
            ->willReturn($this->passwordHasher);

        $this->service = new AuthService(
            entityManager: $this->entityManager,
            userRepository: $this->userRepository,
            passwordHasherFactory: $this->passwordHasherFactory,
            refreshTokenService: $this->refreshTokenService,
            jwtSecret: 'secret',
        );
    }

    public function testUserEnumerationProtection(): void
    {
        // GIVEN an unverified user
        $user = new User('unverified@example.com', 'hashed_password', null, false); // isVerified = false

        $this->userRepository
            ->method('findOneByEmailCaseInsensitive')
            ->with('unverified@example.com')
            ->willReturn($user);

        // AND the password verification fails
        $this->passwordHasher
            ->expects(self::once())
            ->method('verify')
            ->willReturn(false);

        $request = new LoginRequestDTO('unverified@example.com', 'WrongPassword');

        // EXPECT "Invalid credentials" exception, NOT "Email not verified"
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('Invalid credentials.');

        // WHEN logging in
        $this->service->login($request);
    }

    public function testTimingAttackProtection(): void
    {
        // GIVEN no user found for email
        $this->userRepository
            ->method('findOneByEmailCaseInsensitive')
            ->with('nonexistent@example.com')
            ->willReturn(null);

        // EXPECT that dummy hash operation is performed
        $this->passwordHasher
            ->expects(self::once())
            ->method('hash')
            ->with('SomePassword')
            ->willReturn('hashed');

        $request = new LoginRequestDTO('nonexistent@example.com', 'SomePassword');

        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('Invalid credentials.');

        // WHEN logging in
        $this->service->login($request);
    }
}
