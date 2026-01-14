<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\PasswordResetService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-test-reset-email',
    description: 'Wysyła testowy email resetu hasła do istniejącego użytkownika.',
)]
final class SendTestResetEmailCommand extends Command
{
    public function __construct(
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
            $io->error(\sprintf('Użytkownik %s nie został znaleziony.', $email));

            return Command::FAILURE;
        }

        $this->passwordResetService->requestPasswordReset($email);

        $io->success(\sprintf('Email resetu hasła został wysłany do %s (sprawdź Mailpit).', $email));

        return Command::SUCCESS;
    }
}
