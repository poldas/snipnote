<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Command\Note\CreateNoteCommand;
use App\Command\Note\UpdateNoteCommand;
use App\Entity\NoteCollaborator;
use App\Entity\NoteVisibility;
use App\Entity\User;
use App\Repository\NoteCollaboratorRepository;
use App\Repository\NoteRepository;
use App\Service\NoteService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class NoteServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private NoteService $noteService;
    private NoteRepository $noteRepository;
    private NoteCollaboratorRepository $collaboratorRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->noteService = $container->get(NoteService::class);
        $this->noteRepository = $container->get(NoteRepository::class);
        $this->collaboratorRepository = $container->get(NoteCollaboratorRepository::class);

        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
        unset($this->entityManager, $this->noteRepository, $this->collaboratorRepository, $this->noteService);
    }

    public function testCreateNotePersistsWithDefaults(): void
    {
        $owner = $this->persistUser('owner@example.com');

        $note = $this->noteService->createNote($owner, new CreateNoteCommand(
            title: 'Title',
            description: 'Description',
            labels: ['work', 'demo'],
            visibility: 'public',
        ));

        $this->entityManager->clear();

        $saved = $this->noteRepository->find($note->getId());

        self::assertNotNull($saved);
        self::assertSame('Title', $saved->getTitle());
        self::assertSame(['work', 'demo'], $saved->getLabels());
        self::assertSame(NoteVisibility::Public, $saved->getVisibility());
        self::assertSame($owner->getId(), $saved->getOwner()->getId());
        self::assertNotEmpty($saved->getUrlToken());
    }

    public function testGetNoteByIdAllowsCollaborator(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $collaborator = $this->persistUser('collab@example.com');

        $note = $this->noteService->createNote($owner, new CreateNoteCommand(
            title: 'Shared',
            description: 'Body',
        ));
        $noteId = $note->getId();

        $this->entityManager->persist(new NoteCollaborator($note, $collaborator->getEmail(), $collaborator));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $fetched = $this->noteService->getNoteById($noteId, $collaborator);

        self::assertSame($noteId, $fetched->getId());
    }

    public function testGetNoteByIdRejectsUnrelatedUser(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $outsider = $this->persistUser('outsider@example.com');

        $note = $this->noteService->createNote($owner, new CreateNoteCommand(
            title: 'Private',
            description: 'Secret',
        ));

        $this->entityManager->clear();

        $this->expectException(AccessDeniedException::class);
        $this->noteService->getNoteById($note->getId(), $outsider);
    }

    public function testGetPublicNoteByTokenReturnsOnlyPublicNotes(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $public = $this->noteService->createNote($owner, new CreateNoteCommand(
            title: 'Public note',
            description: 'Visible',
            visibility: 'public',
        ));
        $private = $this->noteService->createNote($owner, new CreateNoteCommand(
            title: 'Private note',
            description: 'Hidden',
            visibility: 'private',
        ));

        $this->entityManager->clear();

        $resolved = $this->noteService->getPublicNoteByToken($public->getUrlToken());
        self::assertSame('Public note', $resolved->getTitle());

        $this->expectException(NotFoundHttpException::class);
        $this->noteService->getPublicNoteByToken($private->getUrlToken());
    }

    public function testGetNotePreviewAllowsOwnerToViewPrivateNote(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $private = $this->noteService->createNote($owner, new CreateNoteCommand(
            title: 'Private note',
            description: 'Hidden',
            visibility: 'private',
        ));

        $this->entityManager->clear();

        $resolved = $this->noteService->getNotePreview($private->getUrlToken(), $owner);
        self::assertSame('Private note', $resolved->getTitle());
    }

    public function testGetNotePreviewDeniesOutsider(): void
    {
        $owner = $this->persistUser('owner@test.com');
        $outsider = $this->persistUser('outsider@test.com');
        $note = $this->noteService->createNote($owner, new CreateNoteCommand(
            title: 'Private',
            description: 'Content',
            visibility: 'private'
        ));

        $this->expectException(AccessDeniedException::class);
        $this->noteService->getNotePreview($note->getUrlToken(), $outsider);
    }

    public function testGetNotePreviewAllowsPublicNoteForGuest(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $public = $this->noteService->createNote($owner, new CreateNoteCommand(
            title: 'Public note',
            description: 'Visible',
            visibility: 'public',
        ));

        $this->entityManager->clear();

        $resolved = $this->noteService->getNotePreview($public->getUrlToken(), null);
        self::assertSame('Public note', $resolved->getTitle());
    }

    public function testUpdateNotePersistsChanges(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $note = $this->noteService->createNote($owner, new CreateNoteCommand(
            title: 'Title',
            description: 'Body',
            labels: ['old'],
            visibility: 'draft',
        ));

        $previousUpdatedAt = $note->getUpdatedAt();

        $updated = $this->noteService->updateNote(
            $note->getId(),
            new UpdateNoteCommand(
                title: 'Updated title',
                description: 'Updated body',
                labels: ['new', 'work'],
                visibility: 'public',
            ),
            $owner
        );

        self::assertSame('Updated title', $updated->getTitle());
        self::assertSame('Updated body', $updated->getDescription());
        self::assertSame(['new', 'work'], $updated->getLabels());
        self::assertSame(NoteVisibility::Public, $updated->getVisibility());
        self::assertNotEquals($previousUpdatedAt, $updated->getUpdatedAt());
    }

    public function testDeleteNoteRemovesCollaborators(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $collaborator = $this->persistUser('collab@example.com');

        $note = $this->noteService->createNote($owner, new CreateNoteCommand(
            title: 'To delete',
            description: 'Will be removed',
        ));
        $noteId = $note->getId();

        $this->entityManager->persist(new NoteCollaborator($note, $collaborator->getEmail(), $collaborator));
        $this->entityManager->flush();

        $this->noteService->deleteNote($noteId, $owner);
        $this->entityManager->clear();

        self::assertNull($this->noteRepository->find($noteId));
        self::assertSame([], $this->collaboratorRepository->findAllByNote($noteId));
    }

    private function persistUser(string $email): User
    {
        $user = new User($email, 'hash');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function resetDatabase(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);
    }
}
