<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\Auth\LoginRequestDTO;
use App\DTO\Auth\LogoutRequestDTO;
use App\DTO\Auth\RefreshTokenRequestDTO;
use App\DTO\Auth\RegisterRequestDTO;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\RefreshTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class AuthServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AuthService $service;
    private RefreshTokenService $refreshTokenService;
    private RefreshTokenRepository $refreshTokenRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(AuthService::class);
        $this->refreshTokenService = $container->get(RefreshTokenService::class);
        $this->refreshTokenRepository = $container->get(RefreshTokenRepository::class);
        $this->userRepository = $container->get(UserRepository::class);

        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        unset($this->entityManager, $this->service, $this->refreshTokenService, $this->refreshTokenRepository, $this->userRepository);
    }

    public function testRegisterCreatesUserAndTokens(): void
    {
        $result = $this->service->register(new RegisterRequestDTO(
            email: 'User@Example.com',
            password: 'StrongPass1',
            acceptTerms: true,
        ));

        self::assertSame('user@example.com', $result['user']->email);
        self::assertFalse($result['user']->isVerified);
        self::assertNotEmpty($result['tokens']->accessToken);
        self::assertNotEmpty($result['tokens']->refreshToken);

        $this->entityManager->clear();
        $storedUser = $this->userRepository->findOneByEmailCaseInsensitive('user@example.com');
        self::assertNotNull($storedUser);
        $refreshToken = $this->refreshTokenRepository->findOneBy(['token' => $result['tokens']->refreshToken]);
        self::assertInstanceOf(RefreshToken::class, $refreshToken);
        self::assertSame($storedUser->getId(), $refreshToken->getUser()->getId());
        self::assertFalse($refreshToken->isRevoked());
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $existing = new User('dup@example.com', $this->hash('pass'));
        $this->entityManager->persist($existing);
        $this->entityManager->flush();

        $this->expectException(ValidationException::class);
        $this->service->register(new RegisterRequestDTO('dup@example.com', 'StrongPass1', true));
    }

    public function testLoginSucceedsForVerifiedUser(): void
    {
        $user = new User('user@example.com', $this->hash('StrongPass1'), isVerified: true);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $result = $this->service->login(new LoginRequestDTO(' user@example.com ', 'StrongPass1'));

        self::assertSame($user->getUuid(), $result['user']->uuid);
        self::assertNotEmpty($result['tokens']->refreshToken);
        $refresh = $this->refreshTokenRepository->findOneBy(['token' => $result['tokens']->refreshToken]);
        self::assertNotNull($refresh);
        self::assertSame($user->getId(), $refresh->getUser()->getId());
    }

    public function testLoginFailsForUnverifiedUser(): void
    {
        $user = new User('user@example.com', $this->hash('StrongPass1'), isVerified: false);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->expectException(AuthenticationException::class);
        $this->service->login(new LoginRequestDTO('user@example.com', 'StrongPass1'));
    }

    public function testLoginFailsForWrongPassword(): void
    {
        $user = new User('user@example.com', $this->hash('StrongPass1'), isVerified: true);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->expectException(AuthenticationException::class);
        $this->service->login(new LoginRequestDTO('user@example.com', 'bad'));
    }

    public function testRefreshRotatesToken(): void
    {
        $user = new User('user@example.com', $this->hash('StrongPass1'), isVerified: true);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $oldToken = $this->refreshTokenService->issue($user);
        $this->entityManager->clear();

        $response = $this->service->refresh(new RefreshTokenRequestDTO($oldToken));

        self::assertNotEmpty($response['tokens']->accessToken);
        self::assertNotSame($oldToken, $response['tokens']->refreshToken);

        $this->entityManager->clear();
        $old = $this->refreshTokenRepository->findOneBy(['token' => $oldToken]);
        self::assertTrue($old?->isRevoked());

        $new = $this->refreshTokenRepository->findOneBy(['token' => $response['tokens']->refreshToken]);
        self::assertNotNull($new);
        self::assertSame($user->getId(), $new->getUser()->getId());
    }

    public function testLogoutRevokesRefreshToken(): void
    {
        $user = new User('user@example.com', $this->hash('StrongPass1'), isVerified: true);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $token = $this->refreshTokenService->issue($user);
        $this->entityManager->clear();

        $this->service->logout(new LogoutRequestDTO($token));

        $this->entityManager->clear();
        $stored = $this->refreshTokenRepository->findOneBy(['token' => $token]);
        self::assertTrue($stored?->isRevoked());
    }

    private function hash(string $password): string
    {
        $hasher = static::getContainer()->get('security.password_hasher_factory')->getPasswordHasher(User::class);

        return $hasher->hash($password);
    }

    private function resetDatabase(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);
    }
}
