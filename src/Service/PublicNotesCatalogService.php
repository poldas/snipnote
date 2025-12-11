<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Note\PublicNoteListItemDto;
use App\DTO\Note\PublicNoteListResponseDto;
use App\DTO\Note\PublicNotesPaginationMetaDto;
use App\DTO\Note\PublicNotesQueryDto;
use App\Entity\Note;
use App\Query\Note\PublicNotesQuery;
use App\Repository\NoteRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicNotesCatalogService
{
    private const EXCERPT_LENGTH = 200;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly NoteRepository $noteRepository,
    ) {}

    public function getPublicNotes(PublicNotesQueryDto $queryDto): PublicNoteListResponseDto
    {
        $ownerId = $this->userRepository->findIdByUuid($queryDto->userUuid);
        if ($ownerId === null) {
            throw new NotFoundHttpException('User not found');
        }

        $query = new PublicNotesQuery(
            ownerId: $ownerId,
            page: $queryDto->page,
            perPage: $queryDto->perPage,
            search: $this->normalize($queryDto->searchQuery),
            labels: $this->normalizeLabels($queryDto->labels),
        );

        $result = $this->noteRepository->findPublicNotesForOwner($query);

        $items = array_map(
            fn(Note $note): PublicNoteListItemDto => new PublicNoteListItemDto(
                title: $note->getTitle(),
                descriptionExcerpt: $this->excerpt($note->getDescription()),
                labels: $note->getLabels(),
                createdAt: $note->getCreatedAt(),
                urlToken: $note->getUrlToken(),
            ),
            $result->items
        );

        $totalPages = (int) ceil($result->total / $queryDto->perPage);

        return new PublicNoteListResponseDto(
            data: $items,
            meta: new PublicNotesPaginationMetaDto(
                page: $queryDto->page,
                perPage: $queryDto->perPage,
                totalItems: $result->total,
                totalPages: $totalPages,
            ),
        );
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param list<string> $labels
     * @return list<string>
     */
    private function normalizeLabels(array $labels): array
    {
        $result = [];
        foreach ($labels as $label) {
            if (!\is_string($label)) {
                continue;
            }
            $normalized = $this->normalize($label);
            if ($normalized !== null) {
                $result[] = $normalized;
            }
        }

        return array_values(array_unique($result));
    }

    private function excerpt(string $description): string
    {
        $trimmed = trim($description);

        if (mb_strlen($trimmed) <= self::EXCERPT_LENGTH) {
            return $trimmed;
        }

        return rtrim(mb_substr($trimmed, 0, self::EXCERPT_LENGTH - 1)) . '…';
    }
}
