<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Controller\Api\PublicNoteController;
use App\Entity\Note;
use App\Entity\User;
use App\Service\NoteService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class PublicNoteControllerTest extends TestCase
{
    public function testGetByTokenReturnsPublicPayload(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $note = new Note($owner, 't', 'd', labels: ['a']);
        $note->setUrlToken('uuid');

        $service = $this->createMock(NoteService::class);
        $service
            ->expects(self::once())
            ->method('getPublicNoteByToken')
            ->with('uuid')
            ->willReturn($note);

        $mapper = new \App\Mapper\PublicNoteJsonMapper();
        $controller = new PublicNoteController($service, $mapper);

        $response = $controller->getByToken('uuid');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('t', $payload['data']['title']);
        self::assertSame(['a'], $payload['data']['labels']);
        self::assertArrayHasKey('created_at', $payload['data']);
    }
}
