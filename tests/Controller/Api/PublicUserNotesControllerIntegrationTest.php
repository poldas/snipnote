<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\DTO\Note\PublicNoteListItemDto;
use App\DTO\Note\PublicNoteListResponseDto;
use App\DTO\Note\PublicNotesPaginationMetaDto;
use App\Service\PublicNotesCatalogService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class PublicUserNotesControllerIntegrationTest extends WebTestCase
{
    public function testListReturnsData(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $service = $this->createMock(PublicNotesCatalogService::class);
        $service->expects(self::once())
            ->method('getPublicNotes')
            ->with(self::callback(static function (\App\DTO\Note\PublicNotesQueryDto $dto): bool {
                return $dto->labels === ['demo', 'kod'];
            }))
            ->willReturn(
                new PublicNoteListResponseDto(
                    data: [
                        new PublicNoteListItemDto(
                            title: 'Title',
                            descriptionExcerpt: 'Excerpt',
                            labels: ['demo'],
                            createdAt: new \DateTimeImmutable('2025-01-01T00:00:00+00:00'),
                            urlToken: 'uuid-123',
                        ),
                    ],
                    meta: new PublicNotesPaginationMetaDto(page: 1, perPage: 20, totalItems: 1, totalPages: 1),
                )
            );

        $container->set(PublicNotesCatalogService::class, $service);

        $client->request('GET', '/api/public/users/550e8400-e29b-41d4-a716-446655440000/notes', [
            'labels' => [' demo ', ' kod '],
        ]);

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Title', $payload['data'][0]['title']);
        self::assertSame(['demo'], $payload['data'][0]['labels']);
        self::assertSame(1, $payload['meta']['total_items']);
        self::assertSame(1, $payload['meta']['total_pages']);
    }

    public function testReturnsBadRequestOnInvalidUuid(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/public/users/not-a-uuid/notes');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Validation failed', $payload['error']);
        self::assertArrayHasKey('userUuid', $payload['details']);
    }
}
