<?php

declare(strict_types=1);

namespace App\DTO\Note;

final readonly class PublicNoteListItemDto
{
    /**
     * @param list<string> $labels
     */
    public function __construct(
        public string $title,
        public string $descriptionExcerpt,
        public array $labels,
        public \DateTimeImmutable $createdAt,
        public string $urlToken,
    ) {
    }
}
