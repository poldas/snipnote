<?php

declare(strict_types=1);

namespace App\Service;

use App\Command\Note\CreateNoteCommand;
use App\Command\Note\UpdateNoteCommand;
use App\Entity\Note;
use App\Entity\NoteVisibility;
use App\Entity\User;
use App\Exception\UuidCollisionException;
use App\Repository\NoteCollaboratorRepository;
use App\Repository\NoteRepository;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

class NoteService
{
    public const MAX_UUID_ATTEMPTS = 3;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NoteRepository $noteRepository,
        private readonly NoteCollaboratorRepository $collaboratorRepository,
    ) {
    }

    public function createNote(User $owner, CreateNoteCommand $command): Note
    {
        $labels = $command->labels;
        $visibility = NoteVisibility::from($command->visibility);

        for ($attempt = 1; $attempt <= self::MAX_UUID_ATTEMPTS; ++$attempt) {
            try {
                $note = new Note(
                    owner: $owner,
                    title: $command->title,
                    description: $command->description,
                    labels: $labels,
                    visibility: $visibility,
                );
                $note->setUrlToken(Uuid::v4()->toRfc4122());

                $this->entityManager->persist($note);
                $this->entityManager->flush();

                return $note;
            } catch (UniqueConstraintViolationException $exception) {
                if (self::MAX_UUID_ATTEMPTS === $attempt) {
                    throw new UuidCollisionException('UUID generation failed after retries', $exception->getCode(), $exception);
                }

                $this->entityManager->clear();
            }
        }

        throw new \RuntimeException('Failed to create note after retries');
    }

    public function getNoteById(int $id, User $requester): Note
    {
        $note = $this->noteRepository->find($id);
        if (null === $note) {
            throw new NotFoundHttpException('Note not found');
        }

        if (!$this->isOwnerOrCollaborator($note, $requester)) {
            throw new AccessDeniedException('You are not allowed to view this note');
        }

        return $note;
    }

    public function getPublicNoteByToken(string $urlToken): Note
    {
        try {
            $note = $this->noteRepository->findOneByUrlToken($urlToken);
        } catch (DBALException $exception) {
            throw new NotFoundHttpException('Note not found', $exception);
        }

        if (null === $note) {
            throw new NotFoundHttpException('Note not found');
        }

        return $note;
    }

    public function getNotePreview(string $urlToken, ?User $user): Note
    {
        try {
            $note = $this->noteRepository->findByUrlToken($urlToken);
        } catch (DBALException $exception) {
            throw new NotFoundHttpException('Note not found', $exception);
        }

        if (null === $note) {
            throw new NotFoundHttpException('Note not found');
        }

        if (NoteVisibility::Draft === $note->getVisibility()) {
            throw new NotFoundHttpException('Note not found');
        }

        if (NoteVisibility::Public === $note->getVisibility()) {
            return $note;
        }

        // Private note handling
        if (null !== $user) {
            if ($this->isOwnerOrCollaborator($note, $user)) {
                return $note;
            }
            throw new AccessDeniedException('You are not allowed to view this note');
        }

        throw new NotFoundHttpException('Note not found');
    }

    public function updateNote(int $id, UpdateNoteCommand $command, User $requester): Note
    {
        $note = $this->getNoteById($id, $requester);

        if (null !== $command->title) {
            $note->setTitle($command->title);
        }

        if (null !== $command->description) {
            $note->setDescription($command->description);
        }

        if (null !== $command->labels) {
            $note->setLabels($command->labels);
        }

        if (null !== $command->visibility) {
            $note->setVisibility(NoteVisibility::from($command->visibility));
        }

        $note->touchUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $note;
    }

    public function deleteNote(int $id, User $requester): void
    {
        $note = $this->noteRepository->find($id);
        if (null === $note) {
            throw new NotFoundHttpException('Note not found');
        }

        if (!$this->isOwner($note, $requester)) {
            throw new AccessDeniedException('Only the owner can delete this note');
        }

        $this->entityManager->remove($note);
        $this->entityManager->flush();
    }

    private function isOwner(Note $note, User $user): bool
    {
        if ($note->getOwner() === $user) {
            return true;
        }

        $ownerId = $note->getOwner()->getId();
        $userId = $user->getId();

        return null !== $ownerId && $ownerId === $userId;
    }

    private function isOwnerOrCollaborator(Note $note, User $user): bool
    {
        if ($this->isOwner($note, $user)) {
            return true;
        }

        return $this->collaboratorRepository->isCollaborator($note, $user);
    }
}
