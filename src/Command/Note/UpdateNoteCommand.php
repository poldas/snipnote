<?php

declare(strict_types=1);

namespace App\Command\Note;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateNoteCommand
{
    public function __construct(
        #[Assert\Length(max: 255)]
        public ?string $title = null,

        #[Assert\Length(max: 100000)]
        public ?string $description = null,

        /**
         * @var list<string>|null
         */
        #[Assert\Type('array')]
        #[Assert\All([
            new Assert\Type('string'),
            new Assert\Length(max: 100),
        ])]
        public ?array $labels = null,

        #[Assert\Choice(choices: ['public', 'private', 'draft'])]
        public ?string $visibility = null,
    ) {}
}
