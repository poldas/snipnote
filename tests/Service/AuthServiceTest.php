<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\Auth\LoginRequestDTO;
use App\DTO\Auth\RegisterRequestDTO;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\RefreshTokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class AuthServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private PasswordHasherFactoryInterface $passwordHasherFactory;
    private PasswordHasherInterface $passwordHasher;
    private RefreshTokenService $refreshTokenService;
    private AuthService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->passwordHasherFactory = $this->createStub(PasswordHasherFactoryInterface::class);
        $this->passwordHasher = $this->createStub(PasswordHasherInterface::class);
        $this->refreshTokenService = $this->createStub(RefreshTokenService::class);

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

    public function testRegisterCreatesUserAndReturnsTokens(): void
    {
        $this->userRepository
            ->method('findOneByEmailCaseInsensitive')
            ->with('user@example.com')
            ->willReturn(null);

        $this->passwordHasher
            ->method('hash')
            ->with('StrongPass1')
            ->willReturn('hashed');

        $this->refreshTokenService
            ->method('issue')
            ->willReturn('refresh-1');

        $request = new RegisterRequestDTO('User@Example.com', 'StrongPass1', false);

        $result = $this->service->register($request);

        self::assertSame('user@example.com', $result['user']->email);
        self::assertFalse($result['user']->isVerified);
        self::assertSame('refresh-1', $result['tokens']->refreshToken);
        self::assertNotEmpty($result['tokens']->accessToken);
    }

    public function testLoginSucceedsForVerifiedUser(): void
    {
        $user = new User('user@example.com', 'hashed', null, true);

        $this->userRepository
            ->method('findOneByEmailCaseInsensitive')
            ->with('user@example.com')
            ->willReturn($user);

        $this->passwordHasher
            ->method('verify')
            ->with($user->getPassword(), 'StrongPass1')
            ->willReturn(true);

        $this->refreshTokenService
            ->method('issue')
            ->willReturn('refresh-2');

        $request = new LoginRequestDTO('user@example.com', 'StrongPass1');

        $result = $this->service->login($request);

        self::assertSame('refresh-2', $result['tokens']->refreshToken);
        self::assertNotEmpty($result['tokens']->accessToken);
        self::assertTrue($result['user']->isVerified);
    }

    public function testLoginFailsWhenUnverified(): void
    {
        $user = new User('user@example.com', 'hashed', null, false);

        $this->userRepository
            ->method('findOneByEmailCaseInsensitive')
            ->with('user@example.com')
            ->willReturn($user);

        $this->passwordHasher
            ->method('verify')
            ->willReturn(true);

        $request = new LoginRequestDTO('user@example.com', 'StrongPass1');

        $this->expectException(AuthenticationException::class);

        $this->service->login($request);
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $this->userRepository
            ->method('findOneByEmailCaseInsensitive')
            ->with('user@example.com')
            ->willReturn(new User('user@example.com', 'hash'));

        $request = new RegisterRequestDTO('user@example.com', 'StrongPass1', false);

        $this->expectException(ValidationException::class);

        $this->service->register($request);
    }
}
