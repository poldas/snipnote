<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Note;
use App\Entity\NoteVisibility;
use App\Entity\User;
use App\Query\Note\ListNotesQuery;
use App\Repository\NoteRepository;
use App\Repository\Result\PaginatedResult;
use App\Service\NotesQueryService;
use PHPUnit\Framework\TestCase;

final class NotesQueryServiceTest extends TestCase
{
    public function testListOwnedNotesMapsResultToDto(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $note = new Note($owner, 'Title', 'Description', labels: ['work'], visibility: NoteVisibility::Private);
        $note->setUrlToken('uuid-123');

        $repository = $this->createMock(NoteRepository::class);
        $repository
            ->expects(self::once())
            ->method('findPaginatedForOwnerWithFilters')
            ->with(self::callback(static function (ListNotesQuery $query): bool {
                return $query->ownerId === 1
                    && $query->page === 2
                    && $query->perPage === 5
                    && $query->q === 'search'
                    && $query->labels === ['work']
                    && $query->visibility === NoteVisibility::Private->value;
            }))
            ->willReturn(new PaginatedResult([$note], 1));

        $service = new NotesQueryService($repository);

        $response = $service->listOwnedNotes(
            new ListNotesQuery(
                ownerId: 1,
                page: 2,
                perPage: 5,
                q: 'search',
                labels: ['work'],
                visibility: NoteVisibility::Private->value,
            )
        );

        self::assertSame(1, $response->meta->total);
        self::assertSame(2, $response->meta->page);
        self::assertSame(5, $response->meta->perPage);
        self::assertCount(1, $response->data);

        $dto = $response->data[0];
        self::assertSame('Title', $dto->title);
        self::assertSame('Description', $dto->description);
        self::assertSame(['work'], $dto->labels);
        self::assertSame('private', $dto->visibility);
        self::assertSame('uuid-123', $dto->urlToken);
    }
}

