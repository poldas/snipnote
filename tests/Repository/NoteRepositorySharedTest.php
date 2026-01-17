<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Note;
use App\Entity\NoteCollaborator;
use App\Entity\NoteVisibility;
use App\Entity\User;
use App\Query\Note\ListNotesQuery;
use App\Repository\NoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NoteRepositorySharedTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private NoteRepository $noteRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->noteRepository = self::getContainer()->get(NoteRepository::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);
    }

    public function testFindPaginatedSharedNotesByEmailOnly(): void
    {
        $owner = new User('owner@example.com', 'pass');
        $collaboratorUser = new User('collab@example.com', 'pass');
        $this->entityManager->persist($owner);
        $this->entityManager->persist($collaboratorUser);

        $note = new Note($owner, 'Shared Note', 'Content', [], NoteVisibility::Private);
        $note->setUrlToken('123e4567-e89b-12d3-a456-426614174001');
        $this->entityManager->persist($note);

        // Collaborator added by email only (user_id is NULL)
        $collab = new NoteCollaborator($note, 'collab@example.com', null);
        $this->entityManager->persist($collab);
        $this->entityManager->flush();

        $query = new ListNotesQuery(
            ownerId: $collaboratorUser->getId() ?? 0,
            page: 1,
            perPage: 10,
            q: null,
            labels: [],
            visibility: 'shared',
            ownerEmail: 'collab@example.com'
        );

        $result = $this->noteRepository->findPaginatedForOwnerWithFilters($query);

        self::assertCount(1, $result->items);
        self::assertSame('Shared Note', $result->items[0]->getTitle());
    }

    public function testNotesAreSortedByUpdatedAtDescending(): void
    {
        $owner = new User('owner@example.com', 'pass');
        $this->entityManager->persist($owner);

        // Note 1 created first, but updated later
        $note1 = new Note($owner, 'Note 1', 'Content', [], NoteVisibility::Private);
        $note1->setUrlToken('111e4567-e89b-12d3-a456-426614174001');

        // Note 2 created second
        $note2 = new Note($owner, 'Note 2', 'Content', [], NoteVisibility::Private);
        $note2->setUrlToken('222e4567-e89b-12d3-a456-426614174001');

        $this->entityManager->persist($note1);
        $this->entityManager->persist($note2);
        $this->entityManager->flush();

        // Update Note 1 to have a more recent updatedAt
        $note1->touchUpdatedAt(new \DateTimeImmutable('+1 minute'));
        $this->entityManager->flush();

        $query = new ListNotesQuery(
            ownerId: $owner->getId() ?? 0,
            page: 1,
            perPage: 10,
            q: null,
            labels: [],
            visibility: 'owner',
            ownerEmail: 'owner@example.com'
        );

        $result = $this->noteRepository->findPaginatedForOwnerWithFilters($query);

        self::assertCount(2, $result->items);
        // Note 1 should be first because it was updated most recently
        self::assertSame('Note 1', $result->items[0]->getTitle());
        self::assertSame('Note 2', $result->items[1]->getTitle());
    }

    public function testPublicNotesAreSortedByUpdatedAtDescending(): void
    {
        $owner = new User('owner@example.com', 'pass');
        $this->entityManager->persist($owner);

        // Note 1 created first
        $note1 = new Note($owner, 'Public 1', 'Content', [], NoteVisibility::Public);
        $note1->setUrlToken('11111111-1111-1111-1111-111111111111');

        // Note 2 created second
        $note2 = new Note($owner, 'Public 2', 'Content', [], NoteVisibility::Public);
        $note2->setUrlToken('22222222-2222-2222-2222-222222222222');

        $this->entityManager->persist($note1);
        $this->entityManager->persist($note2);
        $this->entityManager->flush();

        // Update Public 1 later
        $note1->touchUpdatedAt(new \DateTimeImmutable('+5 minutes'));
        $this->entityManager->flush();

        $query = new \App\Query\Note\PublicNotesQuery(
            ownerId: $owner->getId() ?? 0,
            page: 1,
            perPage: 10,
            search: null,
            labels: []
        );

        $result = $this->noteRepository->findPublicNotesForOwner($query);

        self::assertCount(2, $result->items);
        self::assertSame('Public 1', $result->items[0]->getTitle());
        self::assertSame('Public 2', $result->items[1]->getTitle());
    }
}
