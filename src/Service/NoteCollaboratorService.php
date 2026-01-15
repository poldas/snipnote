<?php

declare(strict_types=1);

namespace App\Service;

use App\Command\Collaborator\AddCollaboratorCommand;
use App\Command\Collaborator\RemoveCollaboratorByEmailCommand;
use App\Command\Collaborator\RemoveCollaboratorByIdCommand;
use App\DTO\Collaborator\CollaboratorCollectionDto;
use App\DTO\Collaborator\NoteCollaboratorDto;
use App\Entity\Note;
use App\Entity\NoteCollaborator;
use App\Entity\User;
use App\Repository\NoteCollaboratorRepository;
use App\Repository\NoteRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class NoteCollaboratorService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NoteRepository $noteRepository,
        private readonly NoteCollaboratorRepository $collaboratorRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function addCollaborator(AddCollaboratorCommand $command, User $currentUser): NoteCollaboratorDto
    {
        $note = $this->getNoteOrThrow($command->noteId);
        $this->assertCanAccessNote($note, $currentUser);

        $normalizedEmail = mb_trim($command->email);

        if ($this->collaboratorRepository->existsForNoteAndEmail($command->noteId, $normalizedEmail)) {
            throw new ConflictHttpException('Collaborator already exists for this note');
        }

        $matchedUser = $this->userRepository->findOneByEmailCaseInsensitive($normalizedEmail);

        try {
            $collaborator = new NoteCollaborator($note, $normalizedEmail, $matchedUser);
            $this->entityManager->persist($collaborator);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new ConflictHttpException('Collaborator already exists for this note', $exception);
        }

        return $this->toDto($collaborator);
    }

    public function removeById(RemoveCollaboratorByIdCommand $command, User $currentUser): void
    {
        $note = $this->getNoteOrThrow($command->noteId);
        $collaborator = $this->collaboratorRepository->findByNoteAndId($command->noteId, $command->collaboratorId);

        if (null === $collaborator) {
            throw new NotFoundHttpException('Collaborator not found for this note');
        }

        $this->assertCanRemove($note, $collaborator, $currentUser);

        $this->entityManager->remove($collaborator);
        $this->entityManager->flush();
    }

    public function removeByEmail(RemoveCollaboratorByEmailCommand $command, User $currentUser): void
    {
        $note = $this->getNoteOrThrow($command->noteId);
        $collaborator = $this->collaboratorRepository->findByNoteAndEmail($command->noteId, $command->email);

        if (null === $collaborator) {
            throw new NotFoundHttpException('Collaborator not found for this note');
        }

        $this->assertCanRemove($note, $collaborator, $currentUser);

        $this->entityManager->remove($collaborator);
        $this->entityManager->flush();
    }

    public function listForNote(int $noteId, User $currentUser): CollaboratorCollectionDto
    {
        $note = $this->getNoteOrThrow($noteId);
        $this->assertCanAccessNote($note, $currentUser);

        $collaborators = $this->collaboratorRepository->findAllByNote($noteId);

        return new CollaboratorCollectionDto(
            noteId: $noteId,
            collaborators: array_map(fn (NoteCollaborator $collaborator): NoteCollaboratorDto => $this->toDto($collaborator), $collaborators),
        );
    }

    private function getNoteOrThrow(int $noteId): Note
    {
        $note = $this->noteRepository->find($noteId);
        if (null === $note) {
            throw new NotFoundHttpException('Note not found');
        }

        return $note;
    }

    private function assertCanAccessNote(Note $note, User $user): void
    {
        if ($note->getOwner() === $user) {
            return;
        }

        if ($this->collaboratorRepository->isCollaborator($note, $user)) {
            return;
        }

        throw new AccessDeniedException('Access denied for this note');
    }

    private function assertCanRemove(Note $note, NoteCollaborator $collaborator, User $currentUser): void
    {
        $owner = $note->getOwner();
        $collabUser = $collaborator->getUser();

        // 1. Protection for the owner: cannot be removed as collaborator
        if (($collabUser !== null && $this->isSameUser($collabUser, $owner)) || mb_strtolower($collaborator->getEmail()) === mb_strtolower($owner->getUserIdentifier())) {
            throw new AccessDeniedException('Owner cannot be removed as collaborator');
        }

        // 2. Owner can remove anyone
        if ($this->isSameUser($currentUser, $owner)) {
            return;
        }

        // 3. Collaborator can remove themselves
        if (($collabUser !== null && $this->isSameUser($collabUser, $currentUser)) || mb_strtolower($collaborator->getEmail()) === mb_strtolower($currentUser->getUserIdentifier())) {
            return;
        }

        throw new AccessDeniedException('You cannot remove this collaborator');
    }

    private function isSameUser(User $u1, User $u2): bool
    {
        if ($u1 === $u2) {
            return true;
        }

        $id1 = $u1->getId();
        $id2 = $u2->getId();

        return null !== $id1 && $id1 === $id2;
    }

    private function toDto(NoteCollaborator $collaborator): NoteCollaboratorDto
    {
        return new NoteCollaboratorDto(
            id: $collaborator->getId() ?? 0,
            noteId: $collaborator->getNote()->getId() ?? 0,
            email: $collaborator->getEmail(),
            userId: $collaborator->getUser()?->getId(),
            createdAt: $collaborator->getCreatedAt(),
        );
    }
}
