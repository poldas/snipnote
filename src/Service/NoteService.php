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

class NoteService
{
    public const MAX_UUID_ATTEMPTS = 3;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NoteRepository $noteRepository,
        private readonly NoteCollaboratorRepository $collaboratorRepository,
    ) {}

    public function createNote(User $owner, CreateNoteCommand $command): Note
    {
        $labels = array_values($command->labels);
        $visibility = NoteVisibility::from($command->visibility);

        for ($attempt = 1; $attempt <= self::MAX_UUID_ATTEMPTS; $attempt++) {
            try {
                $note = new Note(
                    owner: $owner,
                    title: $command->title,
                    description: $command->description,
                    labels: $labels,
                    visibility: $visibility,
                );
                $note->setUrlToken($this->generateUuidV4());

                $this->entityManager->persist($note);
                $this->entityManager->flush();

                return $note;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === self::MAX_UUID_ATTEMPTS) {
                    throw new UuidCollisionException('UUID generation failed after retries', $exception->getCode(), $exception);
                }

                $this->entityManager->clear();
            }
        }

        throw new RuntimeException('Failed to create note after retries');
    }

    public function getNoteById(int $id, User $requester): Note
    {
        $note = $this->noteRepository->find($id);
        if ($note === null) {
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

        if ($note === null) {
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

        if ($note === null) {
            throw new NotFoundHttpException('Note not found');
        }

        if ($note->getVisibility() === NoteVisibility::Draft) {
            throw new NotFoundHttpException('Note not found');
        }

        if ($note->getVisibility() === NoteVisibility::Public) {
            return $note;
        }

        // Private note handling
        if ($user !== null) {
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

        if ($command->title !== null) {
            $note->setTitle($command->title);
        }

        if ($command->description !== null) {
            $note->setDescription($command->description);
        }

        if ($command->labels !== null) {
            $note->setLabels(array_values($command->labels));
        }

        if ($command->visibility !== null) {
            $note->setVisibility(NoteVisibility::from($command->visibility));
        }

        $note->touchUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $note;
    }

    public function deleteNote(int $id, User $requester): void
    {
        $note = $this->noteRepository->find($id);
        if ($note === null) {
            throw new NotFoundHttpException('Note not found');
        }

        if ($note->getOwner() !== $requester) {
            throw new AccessDeniedException('Only the owner can delete this note');
        }

        $this->entityManager->remove($note);
        $this->entityManager->flush();
    }

    private function isOwnerOrCollaborator(Note $note, User $user): bool
    {
        if ($note->getOwner() === $user || ($note->getOwner()->getId() !== null && $note->getOwner()->getId() === $user->getId())) {
            return true;
        }

        return $this->collaboratorRepository->isCollaborator($note, $user);
    }

    private function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
