<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetServiceTest extends TestCase
{
    public function testRequestPasswordResetGeneratesTokenAndSendsEmail(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('test@example.com');
        $user->expects($this->once())->method('setResetToken');

        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('findOneByEmailCaseInsensitive')->willReturn($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://localhost/reset-password/token');

        $passwordHasherFactory = self::createStub(PasswordHasherFactoryInterface::class);
        $logger = self::createStub(LoggerInterface::class);

        $service = new PasswordResetService(
            $userRepository,
            $entityManager,
            $mailer,
            $urlGenerator,
            $passwordHasherFactory,
            $logger,
            'no-reply@example.com'
        );

        $service->requestPasswordReset('test@example.com');
    }

    public function testRequestPasswordResetDoesNothingIfUserNotFound(): void
    {
        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('findOneByEmailCaseInsensitive')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $service = new PasswordResetService(
            $userRepository,
            $entityManager,
            $mailer,
            self::createStub(UrlGeneratorInterface::class),
            self::createStub(PasswordHasherFactoryInterface::class),
            self::createStub(LoggerInterface::class),
            'no-reply@example.com'
        );

        $service->requestPasswordReset('nonexistent@example.com');
    }

    public function testResetPasswordUpdatesHashAndClearsToken(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())->method('setPasswordHash')->with('new_hashed_password');
        $user->expects($this->once())->method('clearResetToken');

        $userRepository = self::createStub(UserRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        // Fixed: configurations for mocks to avoid notices
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $urlGenerator = self::createStub(UrlGeneratorInterface::class);

        $passwordHasher = self::createStub(\Symfony\Component\PasswordHasher\PasswordHasherInterface::class);
        $passwordHasher->method('hash')->willReturn('new_hashed_password');

        $passwordHasherFactory = self::createStub(PasswordHasherFactoryInterface::class);
        $passwordHasherFactory->method('getPasswordHasher')->willReturn($passwordHasher);

        $logger = self::createStub(LoggerInterface::class);

        $service = new PasswordResetService(
            $userRepository,
            $entityManager,
            $mailer,
            $urlGenerator,
            $passwordHasherFactory,
            $logger,
            'no-reply@example.com'
        );

        $service->resetPassword($user, 'NewPassword123!');
    }
}
