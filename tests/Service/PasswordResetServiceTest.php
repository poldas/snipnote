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

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneByEmailCaseInsensitive')->willReturn($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://localhost/reset-password/token');

        $passwordHasherFactory = $this->createStub(PasswordHasherFactoryInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

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
}
