<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ApiValidationTest extends WebTestCase
{
    private function createAuthenticatedClient(User $user): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();
        $container = static::getContainer();

        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);
        $userRepository->method('findOneByEmailCaseInsensitive')->willReturn($user);
        $container->set(UserRepository::class, $userRepository);

        return $client;
    }

    private function createJwtForUser(User $user): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'test-jwt-secret';
        $header = $this->base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode((string) json_encode([
            'sub' => $user->getUuid(),
            'exp' => time() + 3600,
        ]));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret, true));

        return "$header.$payload.$signature";
    }

    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    public function testCreateNoteReturns400OnMalformedJson(): void
    {
        $user = new User('user@example.com', 'hash');
        $client = $this->createAuthenticatedClient($user);

        $client->request(
            'POST',
            '/api/notes',
            [],
            [],
            [
                'HTTP_Authorization' => 'Bearer '.$this->createJwtForUser($user),
                'CONTENT_TYPE' => 'application/json',
            ],
            '{"title": "Test", "broken": ' // Missing brace and value
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('Validation failed', $data['error']);
        self::assertSame('Invalid JSON payload', $data['details']['_request'][0]);
    }

    public function testCreateNoteReturns400OnMissingTitle(): void
    {
        $user = new User('user@example.com', 'hash');
        $client = $this->createAuthenticatedClient($user);

        $client->request(
            'POST',
            '/api/notes',
            [],
            [],
            [
                'HTTP_Authorization' => 'Bearer '.$this->createJwtForUser($user),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['description' => 'Content only'])
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('title', $data['details']);
    }

    public function testCreateNoteReturns400OnTooLongTitle(): void
    {
        $user = new User('user@example.com', 'hash');
        $client = $this->createAuthenticatedClient($user);

        $client->request(
            'POST',
            '/api/notes',
            [],
            [],
            [
                'HTTP_Authorization' => 'Bearer '.$this->createJwtForUser($user),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'title' => str_repeat('A', 256), // Max is 255
                'description' => 'Content',
            ])
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('title', $data['details']);
    }

    public function testUpdateNoteReturns400OnInvalidVisibility(): void
    {
        $user = new User('user@example.com', 'hash');
        $client = $this->createAuthenticatedClient($user);

        $client->request(
            'PATCH',
            '/api/notes/1',
            [],
            [],
            [
                'HTTP_Authorization' => 'Bearer '.$this->createJwtForUser($user),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['visibility' => 'invalid_enum_value'])
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegisterReturns400OnInvalidEmail(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'not-an-email',
                'password' => 'password123',
            ])
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('email', $data['details']);
    }
}
