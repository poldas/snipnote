<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Command\Collaborator\AddCollaboratorCommand;
use App\Command\Collaborator\RemoveCollaboratorByEmailCommand;
use App\Command\Collaborator\RemoveCollaboratorByIdCommand;
use App\Entity\Note;
use App\Entity\NoteVisibility;
use App\Entity\User;
use App\Repository\NoteCollaboratorRepository;
use App\Repository\NoteRepository;
use App\Service\NoteCollaboratorService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class NoteCollaboratorServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private NoteCollaboratorService $service;
    private NoteRepository $noteRepository;
    private NoteCollaboratorRepository $collaboratorRepository;
    /** @var list<string> */
    private array $uuidPool = [
        '550e8400-e29b-41d4-a716-446655440021',
        '550e8400-e29b-41d4-a716-446655440022',
        '550e8400-e29b-41d4-a716-446655440023',
        '550e8400-e29b-41d4-a716-446655440024',
        '550e8400-e29b-41d4-a716-446655440025',
    ];
    private int $uuidIndex = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(NoteCollaboratorService::class);
        $this->noteRepository = $container->get(NoteRepository::class);
        $this->collaboratorRepository = $container->get(NoteCollaboratorRepository::class);

        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        unset($this->entityManager, $this->service, $this->noteRepository, $this->collaboratorRepository);
    }

    public function testOwnerAddsCollaboratorAndLinksExistingUser(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $collaborator = $this->persistUser('collab@example.com');
        $note = $this->persistNote($owner, 'Shared note');

        $dto = $this->service->addCollaborator(
            new AddCollaboratorCommand($note->getId(), '  Collab@example.com '),
            $owner
        );

        self::assertSame($note->getId(), $dto->noteId);
        self::assertSame('Collab@example.com', $dto->email);
        self::assertSame($collaborator->getId(), $dto->userId);

        $this->entityManager->clear();
        $found = $this->collaboratorRepository->findByNoteAndEmail($note->getId(), 'collab@example.com');
        self::assertNotNull($found);
        self::assertSame($collaborator->getId(), $found->getUser()?->getId());
    }

    public function testAddCollaboratorRejectsDuplicate(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $note = $this->persistNote($owner, 'Note');

        $this->service->addCollaborator(new AddCollaboratorCommand($note->getId(), 'dup@example.com'), $owner);

        $this->expectException(ConflictHttpException::class);
        $this->service->addCollaborator(new AddCollaboratorCommand($note->getId(), 'dup@example.com'), $owner);
    }

    public function testAddCollaboratorDeniesOutsider(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $outsider = $this->persistUser('outsider@example.com');
        $note = $this->persistNote($owner, 'Note');

        $this->expectException(AccessDeniedException::class);
        $this->service->addCollaborator(new AddCollaboratorCommand($note->getId(), 'x@example.com'), $outsider);
    }

    public function testListForNoteRequiresAccess(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $outsider = $this->persistUser('outsider@example.com');
        $note = $this->persistNote($owner, 'Note');

        $this->expectException(AccessDeniedException::class);
        $this->service->listForNote($note->getId(), $outsider);
    }

    public function testListForNoteReturnsCollection(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $note = $this->persistNote($owner, 'Note');

        $collab1 = $this->persistUser('a@example.com');
        $collab2 = $this->persistUser('b@example.com');

        $this->service->addCollaborator(new AddCollaboratorCommand($note->getId(), $collab1->getEmail()), $owner);
        $this->service->addCollaborator(new AddCollaboratorCommand($note->getId(), $collab2->getEmail()), $owner);

        $collection = $this->service->listForNote($note->getId(), $owner);

        self::assertCount(2, $collection->collaborators);
        $emails = array_map(static fn($dto) => $dto->email, $collection->collaborators);
        self::assertEqualsCanonicalizing(['a@example.com', 'b@example.com'], $emails);
    }

    public function testRemoveByIdNotFoundThrows(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $note = $this->persistNote($owner, 'Note');

        $this->expectException(NotFoundHttpException::class);
        $this->service->removeById(new RemoveCollaboratorByIdCommand($note->getId(), 999), $owner);
    }

    public function testRemoveByEmailAllowsSelfRemoval(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $collaborator = $this->persistUser('collab@example.com');
        $note = $this->persistNote($owner, 'Note');

        $this->service->addCollaborator(new AddCollaboratorCommand($note->getId(), $collaborator->getEmail()), $owner);

        $this->service->removeByEmail(new RemoveCollaboratorByEmailCommand($note->getId(), $collaborator->getEmail()), $collaborator);

        $this->entityManager->clear();
        $remaining = $this->collaboratorRepository->findAllByNote($note->getId());
        self::assertCount(0, $remaining);
    }

    private function persistUser(string $email): User
    {
        $user = new User($email, 'hash');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function persistNote(User $owner, string $title): Note
    {
        $note = new Note($owner, $title, 'body', visibility: NoteVisibility::Private);
        $token = $this->uuidPool[$this->uuidIndex % \count($this->uuidPool)];
        $this->uuidIndex++;
        $note->setUrlToken($token);

        $this->entityManager->persist($note);
        $this->entityManager->flush();

        return $note;
    }

    private function resetDatabase(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);
    }
}
