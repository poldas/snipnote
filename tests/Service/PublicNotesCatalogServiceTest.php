<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\Note\PublicNotesQueryDto;
use App\Entity\Note;
use App\Entity\User;
use App\Query\Note\PublicNotesQuery;
use App\Repository\NoteRepository;
use App\Repository\Result\PaginatedResult;
use App\Repository\UserRepository;
use App\Service\PublicNotesCatalogService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PublicNotesCatalogServiceTest extends TestCase
{
    public function testThrowsWhenUserNotFound(): void
    {
        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('findIdByUuid')->willReturn(null);

        $noteRepository = self::createStub(NoteRepository::class);

        $service = new PublicNotesCatalogService($userRepository, $noteRepository);

        self::expectException(NotFoundHttpException::class);

        $service->getPublicNotes(new PublicNotesQueryDto(userUuid: '550e8400-e29b-41d4-a716-446655440000'));
    }

    public function testReturnsMappedNotes(): void
    {
        $user = new User('user@example.com', 'hash');
        $this->setId($user, 1);

        $note = new Note($user, 'Title', 'Long description text that will be trimmed for excerpt.', ['work']);
        $note->setUrlToken('uuid-123');

        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('findIdByUuid')->willReturn(1);

        $noteRepository = $this->createMock(NoteRepository::class);
        $noteRepository
            ->expects(self::once())
            ->method('findPublicNotesForOwner')
            ->with(self::callback(static function (PublicNotesQuery $query): bool {
                return 1 === $query->ownerId
                    && 2 === $query->page
                    && 2 === $query->perPage
                    && 'hello' === $query->search
                    && $query->labels === ['work', 'demo'];
            }))
            ->willReturn(new PaginatedResult([$note], 3));

        $service = new PublicNotesCatalogService($userRepository, $noteRepository);

        $response = $service->getPublicNotes(new PublicNotesQueryDto(
            userUuid: '550e8400-e29b-41d4-a716-446655440000',
            page: 2,
            perPage: 2,
            searchQuery: '  hello  ',
            labels: ['work', 'demo'],
        ));

        self::assertCount(1, $response->data);
        self::assertSame('Title', $response->data[0]->title);
        self::assertSame('uuid-123', $response->data[0]->urlToken);
        self::assertSame(['work'], $response->data[0]->labels);
        self::assertSame(2, $response->meta->page);
        self::assertSame(2, $response->meta->perPage);
        self::assertSame(3, $response->meta->totalItems);
        self::assertSame(2, $response->meta->totalPages);
    }

    private function setId(User $user, int $id): void
    {
        $ref = new \ReflectionProperty($user, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);
    }
}
