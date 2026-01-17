<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:test-user-manage',
    description: 'Creates or deletes a test user for E2E tests.',
)]
final class TestUserManageCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action: create or delete')
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('password', InputArgument::OPTIONAL, 'User password (required for create)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        if ('create' === $action) {
            if (null === $password || '' === $password) {
                $io->error('Password is required for create action.');

                return Command::FAILURE;
            }

            $existingUser = $this->userRepository->findOneByEmailCaseInsensitive($email);
            if (null !== $existingUser) {
                $this->entityManager->remove($existingUser);
                $this->entityManager->flush();
            }

            // Create a temporary user object just to hash the password correctly
            // (Standard practice in Symfony to hash against the user object)
            $user = new User($email, 'temporary_hash');
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);

            // Re-create user with correct hash
            $user = new User($email, $hashedPassword, isVerified: true);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success(\sprintf('Test user %s created successfully.', $email));

            return Command::SUCCESS;
        }

        if ('delete' === $action) {
            $user = $this->userRepository->findOneByEmailCaseInsensitive($email);
            if (null !== $user) {
                $this->entityManager->remove($user);
                $this->entityManager->flush();
                $io->success(\sprintf('Test user %s deleted successfully.', $email));
            } else {
                $io->note(\sprintf('Test user %s not found, skipping delete.', $email));
            }

            return Command::SUCCESS;
        }

        $io->error('Invalid action. Use "create" or "delete".');

        return Command::INVALID;
    }
}
