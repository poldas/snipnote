<?php

declare(strict_types=1);

namespace App\Command\Note;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateNoteCommand
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $title,

        #[Assert\NotBlank]
        #[Assert\Length(max: 100000)]
        public string $description,

        /**
         * @var list<string>
         */
        #[Assert\Type('array')]
        #[Assert\All([
            new Assert\Type('string'),
            new Assert\Length(max: 100),
        ])]
        public array $labels = [],

        #[Assert\Choice(choices: ['public', 'private', 'draft'])]
        public string $visibility = 'private',
    ) {}
}
