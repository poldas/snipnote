<?php

declare(strict_types=1);

namespace App\DTO\Note;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PublicNotesQueryDto
{
    public const DEFAULT_PAGE = 1;
    public const DEFAULT_PER_PAGE = 50;
    public const MAX_PER_PAGE = 100;

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $userUuid,
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $page = self::DEFAULT_PAGE,
        #[Assert\NotBlank]
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(self::MAX_PER_PAGE)]
        public int $perPage = self::DEFAULT_PER_PAGE,
        #[Assert\Length(max: 255)]
        public ?string $searchQuery = null,

        /**
         * @var list<string>
         */
        #[Assert\Type('array')]
        #[Assert\All([
            new Assert\Type('string'),
            new Assert\Length(min: 1, max: 64),
        ])]
        public array $labels = [],
    ) {
    }
}
