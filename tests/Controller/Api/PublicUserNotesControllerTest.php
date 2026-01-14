<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Controller\Api\PublicUserNotesController;
use App\DTO\Note\PublicNoteListItemDto;
use App\DTO\Note\PublicNoteListResponseDto;
use App\DTO\Note\PublicNotesPaginationMetaDto;
use App\DTO\Note\PublicNotesQueryDto;
use App\Exception\ValidationException;
use App\Mapper\PublicNoteJsonMapper;
use App\Service\PublicNotesCatalogService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PublicUserNotesControllerTest extends TestCase
{
    public function testReturnsData(): void
    {
        $service = $this->createMock(PublicNotesCatalogService::class);
        $service
            ->expects(self::once())
            ->method('getPublicNotes')
            ->with(self::callback(static function (PublicNotesQueryDto $dto): bool {
                return '550e8400-e29b-41d4-a716-446655440000' === $dto->userUuid
                    && 2 === $dto->page
                    && 5 === $dto->perPage
                    && 'hello' === $dto->searchQuery
                    && $dto->labels === ['demo', 'kod'];
            }))
            ->willReturn(new PublicNoteListResponseDto(
                data: [
                    new PublicNoteListItemDto(
                        title: 'Title',
                        descriptionExcerpt: 'Excerpt',
                        labels: ['work'],
                        createdAt: new \DateTimeImmutable('2025-01-01T00:00:00+00:00'),
                        urlToken: 'uuid-123',
                    ),
                ],
                meta: new PublicNotesPaginationMetaDto(page: 2, perPage: 5, totalItems: 10, totalPages: 2),
            ));

        $validator = self::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $mapper = new PublicNoteJsonMapper();
        $controller = new PublicUserNotesController($service, $mapper, $validator);

        $request = new Request(query: [
            'page' => 2,
            'per_page' => 5,
            'q' => ' hello ',
            'labels' => [' demo ', ' kod '],
        ]);

        $response = $controller->list($request, '550e8400-e29b-41d4-a716-446655440000');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('Title', $payload['data'][0]['title']);
        self::assertSame('Excerpt', $payload['data'][0]['description_excerpt']);
        self::assertSame(['work'], $payload['data'][0]['labels']);
        self::assertSame('uuid-123', $payload['data'][0]['url_token']);
        self::assertSame(2, $payload['meta']['page']);
        self::assertSame(5, $payload['meta']['per_page']);
        self::assertSame(10, $payload['meta']['total_items']);
        self::assertSame(2, $payload['meta']['total_pages']);
    }

    public function testValidationExceptionOnInvalidParams(): void
    {
        $service = self::createStub(PublicNotesCatalogService::class);
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Invalid', null, [], null, 'page', null),
        ]);
        $validator = self::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn($violations);

        $mapper = new PublicNoteJsonMapper();
        $controller = new PublicUserNotesController($service, $mapper, $validator);

        $request = new Request(query: ['page' => 0]);

        self::expectException(ValidationException::class);

        $controller->list($request, '550e8400-e29b-41d4-a716-446655440000');
    }
}
