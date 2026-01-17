<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Command\Note\CreateNoteCommand;
use App\Command\Note\UpdateNoteCommand;
use App\Controller\Api\NoteController;
use App\DTO\Note\NoteSummaryDto;
use App\DTO\Note\NotesListResponseDto;
use App\DTO\Note\PaginationMetaDto;
use App\Entity\Note;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Query\Note\ListNotesQuery;
use App\Service\NoteService;
use App\Service\NotesQueryService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class NoteControllerTest extends TestCase
{
    public function testCreateReturnsCreatedNote(): void
    {
        $user = new User('user@example.com', 'hash');
        $note = new Note($user, 't', 'd', labels: ['x']);
        $note->setUrlToken('uuid');

        $noteService = $this->createMock(NoteService::class);
        $noteService
            ->expects(self::once())
            ->method('createNote')
            ->with($user, self::isInstanceOf(CreateNoteCommand::class))
            ->willReturn($note);

        $notesQueryService = self::createStub(NotesQueryService::class);

        $validator = self::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $controller = new NoteController($noteService, $notesQueryService, $validator);

        $request = new Request(content: json_encode([
            'title' => 't',
            'description' => 'd',
            'labels' => ['x'],
        ]));

        $response = $controller->create($request, $user);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('t', $data['data']['title']);
        self::assertSame(['x'], $data['data']['labels']);
    }

    public function testUpdateDelegatesToService(): void
    {
        $user = new User('user@example.com', 'hash');
        $note = new Note($user, 't', 'd');
        $note->setUrlToken('uuid');

        $noteService = $this->createMock(NoteService::class);
        $noteService
            ->expects(self::once())
            ->method('updateNote')
            ->with(1, self::isInstanceOf(UpdateNoteCommand::class), $user)
            ->willReturn($note);

        $notesQueryService = self::createStub(NotesQueryService::class);

        $validator = self::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $controller = new NoteController($noteService, $notesQueryService, $validator);

        $request = new Request(content: json_encode([
            'title' => 'updated',
        ]));

        $response = $controller->update(1, $request, $user);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testInvalidJsonTriggersValidationException(): void
    {
        $user = new User('user@example.com', 'hash');

        $noteService = self::createStub(NoteService::class);
        $notesQueryService = self::createStub(NotesQueryService::class);
        $validator = self::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $controller = new NoteController($noteService, $notesQueryService, $validator);

        $request = new Request(content: '{bad json');

        self::expectException(ValidationException::class);
        $controller->create($request, $user);
    }

    public function testListReturnsPaginatedNotes(): void
    {
        $user = new User('user@example.com', 'hash');
        $this->setUserId($user, 1);

        $noteSummary = new NoteSummaryDto(
            id: 1,
            urlToken: 'uuid-123',
            title: 'Title',
            description: 'Description',
            labels: ['work'],
            visibility: 'private',
            createdAt: new \DateTimeImmutable('2025-01-01T00:00:00+00:00'),
            updatedAt: new \DateTimeImmutable('2025-01-02T00:00:00+00:00'),
        );

        $responseDto = new NotesListResponseDto(
            data: [$noteSummary],
            meta: new PaginationMetaDto(page: 2, perPage: 5, total: 10),
        );

        $noteService = self::createStub(NoteService::class);

        $notesQueryService = $this->createMock(NotesQueryService::class);
        $notesQueryService
            ->expects(self::once())
            ->method('listOwnedNotes')
            ->with(self::callback(static function (ListNotesQuery $query): bool {
                return 1 === $query->ownerId
                    && 2 === $query->page
                    && 5 === $query->perPage
                    && 'hello' === $query->q
                    && $query->labels === ['work', 'dev'];
            }))
            ->willReturn($responseDto);

        $validator = self::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $controller = new NoteController($noteService, $notesQueryService, $validator);

        $request = new Request(query: [
            'page' => 2,
            'per_page' => 5,
            'q' => 'hello',
            'label' => ['work', 'dev'],
        ]);

        $response = $controller->list($request, $user);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $data['data'][0]['id']);
        self::assertSame('uuid-123', $data['data'][0]['url_token']);
        self::assertSame(['work'], $data['data'][0]['labels']);
        self::assertSame(2, $data['meta']['page']);
        self::assertSame(5, $data['meta']['per_page']);
        self::assertSame(10, $data['meta']['total']);
    }

    private function setUserId(User $user, int $id): void
    {
        $ref = new \ReflectionProperty($user, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);
    }
}
