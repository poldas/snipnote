<?php

declare(strict_types=1);

namespace App\DTO\Note;

final readonly class PublicNoteListResponseDto
{
    /**
     * @param list<PublicNoteListItemDto> $data
     */
    public function __construct(
        public array $data,
        public PublicNotesPaginationMetaDto $meta,
    ) {
    }
}
