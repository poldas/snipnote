<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:send-test-verification-email',
    description: 'Sends the verification email using the current mailer config (same flow as registration).'
)]
final class SendTestVerificationEmailCommand extends Command
{
    public function __construct(
        private readonly EmailVerificationService $verificationService,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email to send verification link to (must exist and be unverified).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');

        $user = $this->userRepository->findOneByEmailCaseInsensitive($email);
        if ($user === null) {
            $output->writeln(sprintf('<error>User %s not found. Create a user first.</error>', $email));
            return Command::FAILURE;
        }

        if ($user->isVerified()) {
            $output->writeln(sprintf('<comment>User %s is already verified; skipping send.</comment>', $email));
            return Command::SUCCESS;
        }

        $this->verificationService->sendForEmail($email);
        $output->writeln(sprintf('<info>Triggered verification email for %s (check mailer logs).</info>', $email));

        return Command::SUCCESS;
    }
}
