<?php

declare(strict_types=1);

namespace App\DTO\Note;

final readonly class PaginationMetaDto
{
    public function __construct(
        public int $page,
        public int $perPage,
        public int $total,
    ) {}
}

