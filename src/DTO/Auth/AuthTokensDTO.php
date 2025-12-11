<?php

declare(strict_types=1);

namespace App\DTO\Auth;

final readonly class AuthTokensDTO
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public int $expiresIn,
    ) {}
}
