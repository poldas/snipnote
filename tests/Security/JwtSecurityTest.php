<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class JwtSecurityTest extends WebTestCase
{
    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private function createAuthenticatedClient(User $user): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();
        $container = static::getContainer();

        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);
        $container->set(UserRepository::class, $userRepository);

        return $client;
    }

    /**
     * Test Case: alg: none attack.
     * Some old or misconfigured libraries might accept tokens with no signature.
     */
    public function testJwtAlgNoneAttackReturns401(): void
    {
        $user = new User('user@example.com', 'hash');
        $client = $this->createAuthenticatedClient($user);

        $header = $this->base64UrlEncode((string) json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode((string) json_encode([
            'sub' => $user->getUuid(),
            'exp' => time() + 3600,
        ]));
        
        // Token without signature (two dots, empty third part)
        $token = "$header.$payload.";

        $client->request('GET', '/api/notes', server: ['HTTP_Authorization' => "Bearer $token"]);
        
        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode(), "JWT alg:none attack should fail");
    }

    /**
     * Test Case: Expired token.
     * Ensure the server rigoously checks the 'exp' claim.
     */
    public function testExpiredJwtReturns401(): void
    {
        $user = new User('user@example.com', 'hash');
        $client = $this->createAuthenticatedClient($user);

        $secret = $_ENV['JWT_SECRET'] ?? 'test-jwt-secret';
        $header = $this->base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode((string) json_encode([
            'sub' => $user->getUuid(),
            'exp' => time() - 3600, // 1 hour AGO
        ]));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret, true));
        $token = "$header.$payload.$signature";

        $client->request('GET', '/api/notes', server: ['HTTP_Authorization' => "Bearer $token"]);
        
        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode(), "Expired JWT should be rejected");
    }

    /**
     * Test Case: Tampered signature.
     * Ensure even a single bit change in signature invalidates the token.
     */
    public function testTamperedSignatureReturns401(): void
    {
        $user = new User('user@example.com', 'hash');
        $client = $this->createAuthenticatedClient($user);

        $secret = $_ENV['JWT_SECRET'] ?? 'test-jwt-secret';
        $header = $this->base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode((string) json_encode([
            'sub' => $user->getUuid(),
            'exp' => time() + 3600,
        ]));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret, true));
        
        // Tamper with the signature (change last character)
        $tamperedSignature = $signature . 'Z'; 
        $token = "$header.$payload.$tamperedSignature";

        $client->request('GET', '/api/notes', server: ['HTTP_Authorization' => "Bearer $token"]);
        
        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode(), "Tampered JWT signature should fail");
    }

    /**
     * Test Case: Missing sub claim.
     * The sub claim identifies the user. Its absence should be an error.
     */
    public function testJwtMissingSubReturns401(): void
    {
        $client = static::createClient();

        $secret = $_ENV['JWT_SECRET'] ?? 'test-jwt-secret';
        $header = $this->base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode((string) json_encode([
            'exp' => time() + 3600,
            // 'sub' is missing!
        ]));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret, true));
        $token = "$header.$payload.$signature";

        $client->request('GET', '/api/notes', server: ['HTTP_Authorization' => "Bearer $token"]);
        
        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode(), "JWT without 'sub' claim should fail");
    }
}
