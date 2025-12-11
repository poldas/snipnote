<?php

declare(strict_types=1);

namespace App\Query\Collaborator;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListCollaboratorsQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $noteId,
    ) {}
}


