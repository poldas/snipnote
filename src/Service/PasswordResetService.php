<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetService
{
    private const TOKEN_TTL_SECONDS = 3600; // 1h

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    public function requestPasswordReset(string $email): void
    {
        $user = $this->userRepository->findOneByEmailCaseInsensitive($email);

        // Security: Always return "success" to avoid enumerating users, but only act if user exists.
        if (null === $user) {
            // Perform a dummy operation to consume time similar to token generation and DB update
            hash_hmac('sha256', random_bytes(32), 'dummy_secret');

            return;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable())->modify(\sprintf('+%d seconds', self::TOKEN_TTL_SECONDS));

        $user->setResetToken($token, $expiresAt);
        $this->entityManager->flush();

        $this->sendResetEmail($user, $token);
    }

    public function validateToken(string $token): ?User
    {
        if ('' === $token) {
            return null;
        }

        $user = $this->userRepository->findOneBy(['resetToken' => $token]);

        if (null === $user) {
            return null;
        }

        if ($user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
            return null;
        }

        return $user;
    }

    public function resetPassword(User $user, string $newPassword): void
    {
        $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);
        $hashedPassword = $hasher->hash($newPassword);

        $user->setPasswordHash($hashedPassword);
        $user->clearResetToken();

        $this->entityManager->flush();
    }

    private function sendResetEmail(User $user, string $token): void
    {
        $url = $this->urlGenerator->generate(
            'app_reset_password_page',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $message = (new TemplatedEmail())
            ->from(new Address('' !== $this->mailerFrom ? $this->mailerFrom : 'no-reply@snipnote.local', 'Snipnote'))
            ->to($user->getEmail())
            ->subject('Zresetuj swoje hasło | Snipnote')
            ->htmlTemplate('emails/password_reset.html.twig')
            ->context([
                'url' => $url,
            ]);

        try {
            $this->mailer->send($message);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send password reset email', [
                'email' => $user->getEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
