<?php

declare(strict_types=1);

namespace App\DTO\Collaborator;

final readonly class NoteCollaboratorDto
{
    public function __construct(
        public int $id,
        public int $noteId,
        public string $email,
        public ?int $userId,
        public \DateTimeImmutable $createdAt,
    ) {}
}
