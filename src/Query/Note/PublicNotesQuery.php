<?php

declare(strict_types=1);

namespace App\Query\Note;

final readonly class PublicNotesQuery
{
    public function __construct(
        public int $ownerId,
        public int $page,
        public int $perPage,
        public ?string $search,
        /** @var list<string> */
        public array $labels,
    ) {}
}
