<?php

declare(strict_types=1);

namespace App\DTO\Note;

final readonly class PublicNoteResponseDTO
{
    public function __construct(
        public string $title,
        public string $description,
        /** @var list<string> */
        public array $labels,
        public string $createdAt,
    ) {
    }
}
