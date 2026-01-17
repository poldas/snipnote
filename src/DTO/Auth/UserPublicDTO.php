<?php

declare(strict_types=1);

namespace App\DTO\Auth;

final readonly class UserPublicDTO
{
    public function __construct(
        public string $uuid,
        public string $email,
        public bool $isVerified,
        /** @var list<string> */
        public array $roles = ['ROLE_USER'],
    ) {
    }
}
