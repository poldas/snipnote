<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Note;
use App\Entity\NoteCollaborator;
use App\Entity\NoteVisibility;
use App\Entity\User;
use App\Query\Note\PublicNotesQuery;
use App\Repository\NoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class NoteRepositoryCatalogTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private NoteRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(NoteRepository::class);

        // Clear database
        $this->em->createQuery('DELETE FROM App\Entity\NoteCollaborator')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Note')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    public function testOwnerSeesOnlyOwnedNotesInCatalog(): void
    {
        // 1. Setup Users
        $owner = new User('owner@test.com', 'hash');
        $this->em->persist($owner);

        $other = new User('other@test.com', 'hash');
        $this->em->persist($other);

        // 2. Setup Notes
        // Note 1: Owned by Owner, Public
        $noteOwnedPublic = new Note($owner, 'Owner Public', 'Desc', [], NoteVisibility::Public);
        $this->em->persist($noteOwnedPublic);

        // Note 2: Owned by Owner, Private
        $noteOwnedPrivate = new Note($owner, 'Owner Private', 'Desc', [], NoteVisibility::Private);
        $this->em->persist($noteOwnedPrivate);

        // Note 3: Owned by Other, Shared with Owner (should NOT appear in Owner's Catalog)
        $noteShared = new Note($other, 'Other Shared', 'Desc', [], NoteVisibility::Private);
        $collab = new NoteCollaborator($noteShared, $owner->getEmail());
        $collab->setUser($owner);
        $this->em->persist($noteShared);
        $this->em->persist($collab);

        $this->em->flush();

        // 3. Test: Owner viewing their own catalog
        $query = new PublicNotesQuery(
            ownerId: (int) $owner->getId(),
            page: 1,
            perPage: 10,
            search: null,
            labels: []
        );
        $result = $this->repository->findForCatalog($owner, $owner, $query);

        self::assertCount(1, $result->items, 'Catalog should contain exactly 1 note (Owned Public only). Private notes are hidden even for owner.');

        $ids = array_map(fn (Note $n) => $n->getTitle(), $result->items);
        self::assertContains('Owner Public', $ids);
        self::assertNotContains('Owner Private', $ids, 'Private notes should not appear in Catalog view.');
        self::assertNotContains('Other Shared', $ids, 'Shared notes should not appear in Catalog view.');
    }

    public function testGuestCannotSearchPrivateNotes(): void
    {
        // Red Path: Security check
        $owner = new User('owner@test.com', 'hash');
        $this->em->persist($owner);

        $notePrivate = new Note($owner, 'Secret Plans', 'Top Secret content', [], NoteVisibility::Private);
        $this->em->persist($notePrivate);

        $notePublic = new Note($owner, 'Public Info', 'Hello World', [], NoteVisibility::Public);
        $this->em->persist($notePublic);

        $this->em->flush();

        // Guest searches for "Secret"
        $query = new PublicNotesQuery(
            ownerId: (int) $owner->getId(),
            page: 1,
            perPage: 10,
            search: 'Secret',
            labels: []
        );

        $result = $this->repository->findForCatalog($owner, null, $query); // Viewer is null (Guest)

        self::assertCount(0, $result->items, 'Guest should find 0 results when searching for private content.');
    }

    public function testGuestSeesOnlyPublicNotes(): void
    {
        $owner = new User('owner@test.com', 'hash');
        $this->em->persist($owner);

        $notePrivate = new Note($owner, 'Private', 'Desc', [], NoteVisibility::Private);
        $this->em->persist($notePrivate);

        $notePublic = new Note($owner, 'Public', 'Desc', [], NoteVisibility::Public);
        $this->em->persist($notePublic);

        $this->em->flush();

        $query = new PublicNotesQuery(
            ownerId: (int) $owner->getId(),
            page: 1,
            perPage: 10,
            search: null,
            labels: []
        );
        $result = $this->repository->findForCatalog($owner, null, $query);

        self::assertCount(1, $result->items);
        self::assertEquals('Public', $result->items[0]->getTitle());
    }
}
