<?php

declare(strict_types=1);

namespace App\DTO\Collaborator;

final readonly class CollaboratorCollectionDto
{
    /**
     * @param list<NoteCollaboratorDto> $collaborators
     */
    public function __construct(
        public int $noteId,
        public array $collaborators,
    ) {}
}


