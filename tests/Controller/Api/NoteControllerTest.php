<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Command\Note\CreateNoteCommand;
use App\Command\Note\UpdateNoteCommand;
use App\Controller\Api\NoteController;
use App\Entity\Note;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Service\NoteService;
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

        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $controller = new NoteController($noteService, $validator);

        $request = new Request(content: json_encode([
            'title' => 't',
            'description' => 'd',
            'labels' => ['x'],
        ]));

        $response = $controller->create($request, $user);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
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

        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $controller = new NoteController($noteService, $validator);

        $request = new Request(content: json_encode([
            'title' => 'updated',
        ]));

        $response = $controller->update(1, $request, $user);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testInvalidJsonTriggersValidationException(): void
    {
        $user = new User('user@example.com', 'hash');

        $noteService = $this->createStub(NoteService::class);
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $controller = new NoteController($noteService, $validator);

        $request = new Request(content: '{bad json');

        $this->expectException(ValidationException::class);
        $controller->create($request, $user);
    }
}
