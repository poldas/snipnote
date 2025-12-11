<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Command\Note\CreateNoteCommand;
use App\Command\Note\UpdateNoteCommand;
use App\Entity\Note;
use App\Entity\NoteVisibility;
use App\Entity\User;
use App\Exception\UuidCollisionException;
use App\Repository\NoteCollaboratorRepository;
use App\Repository\NoteRepository;
use App\Service\NoteService;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class NoteServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private NoteRepository $noteRepository;
    private NoteCollaboratorRepository $collaboratorRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->noteRepository = $this->createStub(NoteRepository::class);
        $this->collaboratorRepository = $this->createStub(NoteCollaboratorRepository::class);
    }

    public function testCreateNoteRetriesOnUuidCollision(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $command = new CreateNoteCommand('t', 'd');
        $driverException = $this->createStub(\Doctrine\DBAL\Driver\Exception::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::exactly(2))
            ->method('persist')
            ->with(self::isInstanceOf(Note::class));

        $entityManager
            ->expects(self::exactly(2))
            ->method('flush')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new UniqueConstraintViolationException($driverException, null)),
                null,
            );

        $entityManager
            ->expects(self::once())
            ->method('clear');

        $service = new NoteService(
            $entityManager,
            $this->noteRepository,
            $this->collaboratorRepository,
        );

        $note = $service->createNote($owner, $command);

        self::assertInstanceOf(Note::class, $note);
        self::assertSame($owner, $note->getOwner());
    }

    public function testCreateNoteGivesUpAfterMaxRetries(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $command = new CreateNoteCommand('t', 'd');
        $driverException = $this->createStub(\Doctrine\DBAL\Driver\Exception::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('persist')
            ->with(self::isInstanceOf(Note::class));

        $entityManager
            ->method('flush')
            ->willThrowException(new UniqueConstraintViolationException($driverException, null));

        $entityManager
            ->expects(self::exactly(NoteService::MAX_UUID_ATTEMPTS - 1))
            ->method('clear');

        $service = new NoteService(
            $entityManager,
            $this->noteRepository,
            $this->collaboratorRepository,
        );

        $this->expectException(UuidCollisionException::class);
        $service->createNote($owner, $command);
    }

    public function testGetNoteByIdThrowsWhenUnauthorized(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $other = new User('other@example.com', 'hash');
        $note = new Note($owner, 't', 'd');
        $this->noteRepository->method('find')->willReturn($note);
        $this->collaboratorRepository->method('isCollaborator')->willReturn(false);

        $service = new NoteService(
            $this->entityManager,
            $this->noteRepository,
            $this->collaboratorRepository,
        );

        $this->expectException(AccessDeniedException::class);
        $service->getNoteById(1, $other);
    }

    public function testGetPublicNoteByTokenRequiresPublicVisibility(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $noteRepository = $this->createMock(NoteRepository::class);
        $noteRepository
            ->expects(self::once())
            ->method('findOneByUrlToken')
            ->with('uuid')
            ->willReturn(null);

        $service = new NoteService(
            $this->entityManager,
            $noteRepository,
            $this->collaboratorRepository,
        );

        $this->expectException(NotFoundHttpException::class);
        $service->getPublicNoteByToken('uuid');
    }

    public function testGetPublicNoteByTokenRejectsInvalidUuid(): void
    {
        $noteRepository = $this->createMock(NoteRepository::class);
        $noteRepository
            ->expects(self::once())
            ->method('findOneByUrlToken')
            ->with('not-a-valid-uuid')
            ->willThrowException(new InvalidArgumentException());

        $service = new NoteService(
            $this->entityManager,
            $noteRepository,
            $this->collaboratorRepository,
        );

        $this->expectException(NotFoundHttpException::class);
        $service->getPublicNoteByToken('not-a-valid-uuid');
    }

    public function testGetPublicNoteByTokenReturnsPublicNote(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $note = new Note($owner, 't', 'd', visibility: NoteVisibility::Public);
        $note->setUrlToken('uuid');

        $noteRepository = $this->createMock(NoteRepository::class);
        $noteRepository
            ->expects(self::once())
            ->method('findOneByUrlToken')
            ->with('uuid')
            ->willReturn($note);

        $service = new NoteService(
            $this->entityManager,
            $noteRepository,
            $this->collaboratorRepository,
        );

        $result = $service->getPublicNoteByToken('uuid');

        self::assertSame($note, $result);
    }

    public function testDeleteNoteRequiresOwner(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $other = new User('other@example.com', 'hash');
        $note = new Note($owner, 't', 'd');

        $this->noteRepository->method('find')->willReturn($note);

        $service = new NoteService(
            $this->entityManager,
            $this->noteRepository,
            $this->collaboratorRepository,
        );

        $this->expectException(AccessDeniedException::class);
        $service->deleteNote(1, $other);
    }
}
