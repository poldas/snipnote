<?php

declare(strict_types=1);

namespace App\DTO\Note;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class NotesMarkdownPreviewRequestDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(max: 100000)]
        public string $description,
    ) {}
}
