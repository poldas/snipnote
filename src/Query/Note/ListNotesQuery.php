<?php

declare(strict_types=1);

namespace App\Query\Note;

final readonly class ListNotesQuery
{
    /**
     * @param list<string> $labels
     */
    public function __construct(
        public int $ownerId,
        public int $page,
        public int $perPage,
        public ?string $q,
        public array $labels = [],
        public string $visibility = 'owner',
        public ?string $ownerEmail = null,
    ) {}
}
