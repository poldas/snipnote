<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Note\NoteSummaryDto;
use App\DTO\Note\NotesListResponseDto;
use App\DTO\Note\PaginationMetaDto;
use App\Entity\Note;
use App\Query\Note\ListNotesQuery;
use App\Repository\NoteRepository;

class NotesQueryService
{
    public function __construct(private readonly NoteRepository $noteRepository) {}

    public function listOwnedNotes(ListNotesQuery $query): NotesListResponseDto
    {
        $result = $this->noteRepository->findPaginatedForOwnerWithFilters($query);

        $items = array_map(
            static fn (Note $note): NoteSummaryDto => new NoteSummaryDto(
                id: $note->getId() ?? 0,
                urlToken: $note->getUrlToken(),
                title: $note->getTitle(),
                description: $note->getDescription(),
                labels: $note->getLabels(),
                visibility: $note->getVisibility()->value,
                createdAt: $note->getCreatedAt(),
                updatedAt: $note->getUpdatedAt(),
            ),
            $result->items
        );

        return new NotesListResponseDto(
            data: $items,
            meta: new PaginationMetaDto(
                page: $query->page,
                perPage: $query->perPage,
                total: $result->total,
            ),
        );
    }
}

