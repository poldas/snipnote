<?php

declare(strict_types=1);

namespace App\Command\Collaborator;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RemoveCollaboratorByIdCommand
{
    public function __construct(
        #[Assert\Positive]
        public int $noteId,

        #[Assert\Positive]
        public int $collaboratorId,
    ) {}
}


