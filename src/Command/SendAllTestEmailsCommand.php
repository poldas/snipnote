<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use App\Service\PasswordResetService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-all-test-emails',
    description: 'Wysyła komplet maili testowych (weryfikacja + reset) na podany adres.',
)]
final class SendAllTestEmailsCommand extends Command
{
    public function __construct(
        private readonly EmailVerificationService $verificationService,
        private readonly PasswordResetService $passwordResetService,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email użytkownika (musi istnieć w bazie).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        $user = $this->userRepository->findOneByEmailCaseInsensitive($email);
        if (null === $user) {
            $io->error(\sprintf('Użytkownik %s nie istnieje.', $email));

            return Command::FAILURE;
        }

        $io->title('Wysyłka kompletnych maili testowych Snipnote');

        $io->text('1. Wysyłka maila weryfikacyjnego...');
        $this->verificationService->sendForEmail($email);

        $io->text('2. Wysyłka maila resetu hasła...');
        $this->passwordResetService->requestPasswordReset($email);

        $io->success('Wszystkie maile testowe zostały wysłane. Sprawdź swoją skrzynkę (np. Mailpit).');

        return Command::SUCCESS;
    }
}
