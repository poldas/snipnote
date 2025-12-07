<?php

declare(strict_types=1);

namespace App\DTO\Note;

final readonly class NoteResponseDTO
{
    public function __construct(
        public int $id,
        public int $ownerId,
        public string $urlToken,
        public string $title,
        public string $description,
        /** @var list<string> */
        public array $labels,
        public string $visibility,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
