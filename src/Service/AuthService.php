<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Auth\AuthTokensDTO;
use App\DTO\Auth\LoginRequestDTO;
use App\DTO\Auth\LogoutRequestDTO;
use App\DTO\Auth\RefreshTokenRequestDTO;
use App\DTO\Auth\RegisterRequestDTO;
use App\DTO\Auth\UserPublicDTO;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class AuthService
{
    private const ACCESS_TOKEN_TTL_SECONDS = 900;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
        private readonly RefreshTokenService $refreshTokenService,
        #[Autowire('%env(JWT_SECRET)%')]
        private readonly string $jwtSecret,
    ) {
    }

    /**
     * @return array{user: UserPublicDTO, tokens: AuthTokensDTO}
     */
    public function register(RegisterRequestDTO $request): array
    {
        $email = mb_strtolower(mb_trim($request->email));
        if ('' === $email) {
            throw new ValidationException(['email' => ['Email nie może być pusty']]);
        }

        if (null !== $this->userRepository->findOneByEmailCaseInsensitive($email)) {
            throw new ValidationException(['email' => ['Email jest już w użyciu']]);
        }

        $passwordHash = $this->hashPassword($request->password);
        $user = new User(email: $email, passwordHash: $passwordHash, isVerified: false);

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ValidationException(['email' => ['Email jest już w użyciu']]);
        }

        $tokens = $this->issueTokens($user);

        return [
            'user' => $this->toPublicUserDto($user),
            'tokens' => $tokens,
        ];
    }

    /**
     * @return array{user: UserPublicDTO, tokens: AuthTokensDTO}
     */
    public function login(LoginRequestDTO $request): array
    {
        $email = mb_strtolower(mb_trim($request->email));
        if ('' === $email) {
            throw new CustomUserMessageAuthenticationException('Invalid credentials.');
        }

        $user = $this->userRepository->findOneByEmailCaseInsensitive($email);
        if (null === $user) {
            // Perform a dummy hash operation to prevent timing attacks
            $this->passwordHasherFactory->getPasswordHasher(User::class)->hash($request->password);

            throw new CustomUserMessageAuthenticationException('Invalid credentials.');
        }

        if (!$this->verifyPassword($user, $request->password)) {
            throw new CustomUserMessageAuthenticationException('Invalid credentials.');
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAuthenticationException('Email not verified.');
        }

        $tokens = $this->issueTokens($user);

        return [
            'user' => $this->toPublicUserDto($user),
            'tokens' => $tokens,
        ];
    }

    /**
     * @return array{tokens: AuthTokensDTO}
     */
    public function refresh(RefreshTokenRequestDTO $request): array
    {
        $rotated = $this->refreshTokenService->rotate($request->refreshToken);

        $tokens = $this->issueTokens($rotated['user'], $rotated['refreshToken']);

        return ['tokens' => $tokens];
    }

    public function logout(LogoutRequestDTO $request): void
    {
        $this->refreshTokenService->revoke($request->refreshToken);
    }

    private function hashPassword(string $plainPassword): string
    {
        $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);

        return $hasher->hash($plainPassword);
    }

    private function verifyPassword(User $user, string $plainPassword): bool
    {
        $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);

        return $hasher->verify($user->getPassword(), $plainPassword);
    }

    private function issueTokens(User $user, ?string $existingRefreshToken = null): AuthTokensDTO
    {
        $expiresAt = time() + self::ACCESS_TOKEN_TTL_SECONDS;
        $accessToken = $this->encodeJwt($this->buildPayload($user, $expiresAt));
        $refreshToken = $existingRefreshToken ?? $this->refreshTokenService->issue($user);

        return new AuthTokensDTO(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresIn: self::ACCESS_TOKEN_TTL_SECONDS,
        );
    }

    public function issueAccessToken(User $user): string
    {
        $expiresAt = time() + self::ACCESS_TOKEN_TTL_SECONDS;

        return $this->encodeJwt($this->buildPayload($user, $expiresAt));
    }

    /**
     * @return array{sub: string, exp: int}
     */
    private function buildPayload(User $user, int $expiresAt): array
    {
        return [
            'sub' => $user->getUuid(),
            'exp' => $expiresAt,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeJwt(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $headerB64 = $this->base64UrlEncode(json_encode($header, \JSON_THROW_ON_ERROR));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload, \JSON_THROW_ON_ERROR));

        $signature = hash_hmac('sha256', $headerB64.'.'.$payloadB64, $this->jwtSecret, true);
        $signatureB64 = $this->base64UrlEncode($signature);

        return \sprintf('%s.%s.%s', $headerB64, $payloadB64, $signatureB64);
    }

    private function base64UrlEncode(string $data): string
    {
        return mb_rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function toPublicUserDto(User $user): UserPublicDTO
    {
        return new UserPublicDTO(
            uuid: $user->getUuid(),
            email: $user->getEmail(),
            isVerified: $user->isVerified(),
            roles: $user->getRoles(),
        );
    }
}
