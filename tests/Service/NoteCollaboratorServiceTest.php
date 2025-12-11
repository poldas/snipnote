<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Command\Collaborator\AddCollaboratorCommand;
use App\Command\Collaborator\RemoveCollaboratorByIdCommand;
use App\Entity\Note;
use App\Entity\User;
use App\Repository\NoteCollaboratorRepository;
use App\Repository\NoteRepository;
use App\Repository\UserRepository;
use App\Service\NoteCollaboratorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class NoteCollaboratorServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private NoteRepository $noteRepository;
    private NoteCollaboratorRepository $collaboratorRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->noteRepository = $this->createStub(NoteRepository::class);
        $this->collaboratorRepository = $this->createStub(NoteCollaboratorRepository::class);
        $this->userRepository = $this->createStub(UserRepository::class);
    }

    public function testAddCollaboratorRequiresOwnerOrCollaborator(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $note = new Note($owner, 't', 'd');
        $requester = new User('outsider@example.com', 'hash');

        $this->noteRepository->method('find')->willReturn($note);
        $this->collaboratorRepository->method('isCollaborator')->willReturn(false);

        $service = new NoteCollaboratorService(
            $this->entityManager,
            $this->noteRepository,
            $this->collaboratorRepository,
            $this->userRepository,
        );

        $this->expectException(AccessDeniedException::class);
        $service->addCollaborator(new AddCollaboratorCommand(1, 'collab@example.com'), $requester);
    }

    public function testAddCollaboratorDetectsDuplicates(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $note = new Note($owner, 't', 'd');

        $this->noteRepository->method('find')->willReturn($note);
        $this->collaboratorRepository
            ->method('existsForNoteAndEmail')
            ->with(1, 'collab@example.com')
            ->willReturn(true);

        $service = new NoteCollaboratorService(
            $this->entityManager,
            $this->noteRepository,
            $this->collaboratorRepository,
            $this->userRepository,
        );

        $this->expectException(ConflictHttpException::class);
        $service->addCollaborator(new AddCollaboratorCommand(1, 'collab@example.com'), $owner);
    }

    public function testCollaboratorCanRemoveSelf(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $collaboratorUser = new User('collab@example.com', 'hash');
        $note = new Note($owner, 't', 'd');
        $collaboratorEntity = new \App\Entity\NoteCollaborator($note, 'collab@example.com', $collaboratorUser);

        $noteRepository = $this->createStub(NoteRepository::class);
        $noteRepository->method('find')->willReturn($note);

        $collaboratorRepository = $this->createStub(NoteCollaboratorRepository::class);
        $collaboratorRepository->method('findByNoteAndId')->willReturn($collaboratorEntity);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($collaboratorEntity);
        $entityManager->expects(self::once())->method('flush');

        $service = new NoteCollaboratorService(
            $entityManager,
            $noteRepository,
            $collaboratorRepository,
            $this->userRepository,
        );

        $service->removeById(new RemoveCollaboratorByIdCommand(1, 5), $collaboratorUser);
    }
}


