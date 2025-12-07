<?php

declare(strict_types=1);

namespace App\DTO\Note;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ListNotesQueryDto
{
    public const DEFAULT_PAGE = 1;
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $page = self::DEFAULT_PAGE,

        #[Assert\NotBlank]
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(self::MAX_PER_PAGE)]
        public int $perPage = self::DEFAULT_PER_PAGE,

        #[Assert\Length(max: 255)]
        public ?string $q = null,

        /**
         * @var list<string>
         */
        #[Assert\Type('array')]
        #[Assert\All([
            new Assert\Type('string'),
            new Assert\Length(max: 64),
        ])]
        public array $labels = [],
    ) {}
}

