<?php

declare(strict_types=1);

namespace App\DTO\Note;

final readonly class NotesListResponseDto
{
    /**
     * @param list<NoteSummaryDto> $data
     */
    public function __construct(
        public array $data,
        public PaginationMetaDto $meta,
    ) {
    }
}
