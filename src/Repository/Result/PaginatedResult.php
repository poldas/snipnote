<?php

declare(strict_types=1);

namespace App\Repository\Result;

use App\Entity\Note;

final readonly class PaginatedResult
{
    /**
     * @param list<Note> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {}
}

