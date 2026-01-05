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

        $this->assertCount(1, $result->items);
        $this->assertSame('Shared Note', $result->items[0]->getTitle());
    }
}
