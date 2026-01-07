<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ValidationException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Lightweight email verification helper for API.
 * Generates HMAC-signed URLs and marks users verified on success.
 * In this MVP we log the link instead of sending real email.
 */
final class EmailVerificationService
{
    private const LINK_TTL_SECONDS = 86400; // 24h

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly MailerInterface $mailer,
        #[Autowire('%env(default::MAILER_FROM)%')]
        private readonly string $mailerFrom,
        #[Autowire('%env(VERIFY_EMAIL_SECRET)%')]
        private readonly string $verificationSecret,
    ) {}

    public function sendForEmail(string $rawEmail): void
    {
        $email = mb_strtolower(trim($rawEmail));
        if ($email === '') {
            return;
        }

        $user = $this->userRepository->findOneByEmailCaseInsensitive($email);
        if ($user === null || $user->isVerified()) {
            return;
        }

        $expires = time() + self::LINK_TTL_SECONDS;
        $signature = $this->sign($email, $expires);

        $url = $this->urlGenerator->generate(
            'app_verify_email',
            ['email' => $email, 'expires' => (string) $expires, 'signature' => $signature],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->sendEmail($email, $url);
    }

    public function handleVerification(string $rawEmail, string $signature, string $expires): void
    {
        $email = mb_strtolower(trim($rawEmail));
        $expiresInt = ctype_digit($expires) ? (int) $expires : 0;

        if ($email === '' || $signature === '' || $expiresInt <= 0) {
            throw new ValidationException(['token' => ['Invalid verification link']]);
        }

        if ($expiresInt < time()) {
            throw new ValidationException(['token' => ['Verification link expired']]);
        }

        $expected = $this->sign($email, $expiresInt);
        if (!hash_equals($expected, $signature)) {
            throw new ValidationException(['token' => ['Invalid verification link']]);
        }

        $user = $this->userRepository->findOneByEmailCaseInsensitive($email);
        if ($user === null) {
            throw new ValidationException(['token' => ['Invalid verification link']]);
        }

        if ($user->isVerified()) {
            return;
        }

        $user->markVerified();
        $this->entityManager->flush();
    }

    private function sign(string $email, int $expires): string
    {
        $payload = sprintf('%s|%d', $email, $expires);

        return hash_hmac('sha256', $payload, $this->verificationSecret);
    }

    private function sendEmail(string $email, string $url): void
    {
        $message = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom ?: 'no-reply@example.com', 'Snipnote'))
            ->to($email)
            ->subject('Potwierdź swój adres e-mail | Snipnote')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'url' => $url,
            ]);

        try {
            $this->mailer->send($message);
            $this->logger->info('Verification email sent', [
                'email' => $email,
                'message_id' => $message->getHeaders()->getHeaderBody('Message-ID'),
                'to' => iterator_to_array($message->getTo()),
            ]);
        } catch (Throwable $e) {
            // Fallback: log and continue (do not fail registration), but try sync send without messenger.
            $this->logger->error('Failed to send verification email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        $this->logger->info('Verification email link generated', [
            'email' => $email,
            'url' => $url,
        ]);
    }
}
