<?php

declare(strict_types=1);

namespace App\Command\Collaborator;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddCollaboratorCommand
{
    public function __construct(
        #[Assert\Positive]
        public int $noteId,
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        public string $email,
    ) {
    }
}
