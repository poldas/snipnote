<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EmailVerificationServiceTest extends TestCase
{
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;
    private LoggerInterface $logger;
    private MailerInterface $mailer;
    private EmailVerificationService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);

        $this->service = new EmailVerificationService(
            userRepository: $this->userRepository,
            entityManager: $this->entityManager,
            urlGenerator: $this->urlGenerator,
            logger: $this->logger,
            mailer: $this->mailer,
            mailerFrom: 'no-reply@example.com',
            verificationSecret: 'secret',
        );
    }

    public function testHandleVerificationMarksUserVerified(): void
    {
        $email = 'user@example.com';
        $expires = (string) (time() + 3600);
        $signature = hash_hmac('sha256', sprintf('%s|%d', $email, (int) $expires), 'secret');

        $user = new User($email, 'hash', null, false);

        $this->userRepository
            ->method('findOneByEmailCaseInsensitive')
            ->with($email)
            ->willReturn($user);

        $this->mailer
            ->expects(self::never())
            ->method('send');

        $this->service->handleVerification($email, $signature, $expires);

        self::assertTrue($user->isVerified());
    }

    public function testHandleVerificationRejectsBadSignature(): void
    {
        $email = 'user@example.com';
        $expires = (string) (time() + 3600);

        $this->expectException(ValidationException::class);

        $this->mailer
            ->expects(self::never())
            ->method('send');

        $this->service->handleVerification($email, 'bad', $expires);
    }

    public function testSendForEmailSkipsVerifiedUser(): void
    {
        $user = new User('user@example.com', 'hash', null, true);

        $this->userRepository
            ->method('findOneByEmailCaseInsensitive')
            ->willReturn($user);

        $this->mailer
            ->expects(self::never())
            ->method('send');

        $this->service->sendForEmail('user@example.com');
    }

    public function testSendForEmailSendsMailForUnverified(): void
    {
        $user = new User('user@example.com', 'hash', null, false);

        $this->userRepository
            ->method('findOneByEmailCaseInsensitive')
            ->willReturn($user);

        $this->urlGenerator
            ->method('generate')
            ->willReturn('https://example.com/verify');

        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function ($email): bool {
                return $email instanceof \Symfony\Bridge\Twig\Mime\TemplatedEmail 
                    && $email->getContext()['url'] === 'https://example.com/verify';
            }));

        $this->service->sendForEmail('user@example.com');
    }
}
