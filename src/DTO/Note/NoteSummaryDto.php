<?php

declare(strict_types=1);

namespace App\DTO\Note;

final readonly class NoteSummaryDto
{
    /**
     * @param list<string> $labels
     */
    public function __construct(
        public int $id,
        public string $urlToken,
        public string $title,
        public string $description,
        public array $labels,
        public string $visibility,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {}
}

