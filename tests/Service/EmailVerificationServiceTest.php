<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EmailVerificationServiceTest extends TestCase
{
    private UserRepository&Stub $userRepository;
    private EntityManagerInterface&Stub $entityManager;
    private UrlGeneratorInterface&Stub $urlGenerator;
    private LoggerInterface&Stub $logger;
    private MailerInterface&MockObject $mailer;
    private EmailVerificationService $service;

    protected function setUp(): void
    {
        $this->userRepository = self::createStub(UserRepository::class);
        $this->entityManager = self::createStub(EntityManagerInterface::class);
        $this->urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $this->logger = self::createStub(LoggerInterface::class);
        $this->mailer = self::createMock(MailerInterface::class);

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
        $signature = hash_hmac('sha256', \sprintf('%s|%d', $email, (int) $expires), 'secret');

        $user = new User($email, 'hash', null, false);

        $this->userRepository
            ->method('findOneByEmailCaseInsensitive')
            ->with($email)
            ->willReturn($user);

        // Expect no emails sent
        $this->mailer->expects(self::never())->method('send');

        $this->service->handleVerification($email, $signature, $expires);

        self::assertTrue($user->isVerified());
    }

    public function testHandleVerificationRejectsBadSignature(): void
    {
        $email = 'user@example.com';
        $expires = (string) (time() + 3600);

        // Expect no emails sent
        $this->mailer->expects(self::never())->method('send');

        self::expectException(ValidationException::class);

        $this->service->handleVerification($email, 'bad', $expires);
    }

    public function testHandleVerificationRejectsExpiredToken(): void
    {
        $email = 'user@example.com';
        $expires = (string) (time() - 100); // 1 minute ago
        $signature = hash_hmac('sha256', \sprintf('%s|%d', $email, (int) $expires), 'secret');

        // Expect no emails sent
        $this->mailer->expects(self::never())->method('send');

        self::expectException(ValidationException::class);

        $this->service->handleVerification($email, $signature, $expires);
    }

    public function testSendForEmailSkipsVerifiedUser(): void
    {
        $user = new User('user@example.com', 'hash', null, true);

        $this->userRepository
            ->method('findOneByEmailCaseInsensitive')
            ->willReturn($user);

        // Expect no emails sent
        $this->mailer->expects(self::never())->method('send');

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
                    && 'https://example.com/verify' === $email->getContext()['url'];
            }));

        $this->service->sendForEmail('user@example.com');
    }
}
