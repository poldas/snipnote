<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final class JwtAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        #[Autowire('%env(JWT_SECRET)%')]
        private readonly string $jwtSecret,
    ) {
    }

    public function supports(Request $request): bool
    {
        $header = $request->headers->get('Authorization', '');

        return str_starts_with($header, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token = $this->extractToken($request);
        [$headerB64, $payloadB64, $signatureB64] = $this->splitToken($token);

        $header = $this->jsonDecode($this->base64UrlDecode($headerB64), 'header');
        $payload = $this->jsonDecode($this->base64UrlDecode($payloadB64), 'payload');

        if (($header['alg'] ?? null) !== 'HS256') {
            throw new CustomUserMessageAuthenticationException('Unsupported JWT algorithm');
        }

        $this->assertNotExpired($payload);
        $this->assertSignature($headerB64, $payloadB64, $signatureB64);

        $subject = $payload['sub'] ?? null;
        if (!\is_string($subject) || '' === $subject) {
            throw new CustomUserMessageAuthenticationException('Invalid JWT subject');
        }

        return new SelfValidatingPassport(
            new UserBadge($subject, function (string $identifier) {
                $criteria = str_contains($identifier, '@')
                    ? ['email' => $identifier]
                    : ['uuid' => $identifier];

                $user = $this->userRepository->findOneBy($criteria);
                if (null === $user) {
                    throw new CustomUserMessageAuthenticationException('User not found for token');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // continue request
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(
            ['error' => 'Unauthorized', 'message' => $exception->getMessage()],
            JsonResponse::HTTP_UNAUTHORIZED
        );
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
    }

    private function extractToken(Request $request): string
    {
        $header = $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            throw new CustomUserMessageAuthenticationException('Missing Authorization header');
        }

        $token = mb_substr($header, 7);
        if ('' === $token) {
            throw new CustomUserMessageAuthenticationException('Empty bearer token');
        }

        return $token;
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function splitToken(string $token): array
    {
        $parts = explode('.', $token);
        if (3 !== \count($parts)) {
            throw new CustomUserMessageAuthenticationException('Malformed JWT');
        }

        return [$parts[0], $parts[1], $parts[2]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertNotExpired(array $payload): void
    {
        if (!isset($payload['exp'])) {
            return;
        }

        if (!\is_int($payload['exp']) || $payload['exp'] <= time()) {
            throw new CustomUserMessageAuthenticationException('Token expired');
        }
    }

    private function assertSignature(string $headerB64, string $payloadB64, string $signatureB64): void
    {
        $data = $headerB64.'.'.$payloadB64;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $data, $this->jwtSecret, true));
        if (!hash_equals($expected, $signatureB64)) {
            throw new CustomUserMessageAuthenticationException('Invalid token signature');
        }
    }

    private function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if (false === $decoded) {
            throw new CustomUserMessageAuthenticationException('Invalid base64 payload');
        }

        return $decoded;
    }

    private function base64UrlEncode(string $data): string
    {
        return mb_rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonDecode(string $json, string $section): array
    {
        try {
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new CustomUserMessageAuthenticationException(\sprintf('Invalid JWT %s', $section), previous: $exception);
        }

        if (!\is_array($decoded)) {
            throw new CustomUserMessageAuthenticationException(\sprintf('Invalid JWT %s structure', $section));
        }

        return $decoded;
    }
}
