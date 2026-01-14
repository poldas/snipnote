<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Note;
use App\Entity\NoteCollaborator;
use App\Entity\User;
use App\Repository\NoteCollaboratorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NoteCollaboratorRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private NoteCollaboratorRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(NoteCollaboratorRepository::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);
    }

    public function testIsCollaboratorMatchesByEmailWhenUserNotLinked(): void
    {
        $owner = new User('owner@example.com', 'pass');
        $collaboratorUser = new User('collab@example.com', 'pass');
        $this->entityManager->persist($owner);
        $this->entityManager->persist($collaboratorUser);

        $note = new Note($owner, 'Title', 'Desc');
        $note->setUrlToken('123e4567-e89b-12d3-a456-426614174005');
        $this->entityManager->persist($note);

        // Link only by email
        $collab = new NoteCollaborator($note, 'collab@example.com', null);
        $this->entityManager->persist($collab);
        $this->entityManager->flush();

        self::assertTrue($this->repository->isCollaborator($note, $collaboratorUser));
    }
}
