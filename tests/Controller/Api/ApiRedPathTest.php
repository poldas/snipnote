<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Note;
use App\Entity\NoteVisibility;
use App\Entity\User;
use App\Repository\NoteRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * RED PATH API TESTS
 * This test suite focuses on negative scenarios: invalid data, unauthorized access, etc.
 */
final class ApiRedPathTest extends WebTestCase
{
    private function createJwtForUser(User $user): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'test-jwt-secret';
        $header = $this->base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        // sub MUST be the UUID
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

    /**
     * @param array<int, Note>|null $mockedNotes
     */
    private function createClientWithUser(User $user, ?array $mockedNotes = null): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();
        $container = static::getContainer();

        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);
        $userRepository->method('findOneByEmailCaseInsensitive')->willReturn($user);
        $container->set(UserRepository::class, $userRepository);

        if (null !== $mockedNotes) {
            $noteRepository = self::createStub(NoteRepository::class);
            $noteRepository->method('find')->willReturnCallback(function ($id) use ($mockedNotes) {
                return $mockedNotes[$id] ?? null;
            });
            $container->set(NoteRepository::class, $noteRepository);
        }

        return $client;
    }

    // --- 401 UNAUTHORIZED ---

    public function testEndpointRequiresValidToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/notes');
        self::assertSame(401, $client->getResponse()->getStatusCode(), 'Should require token');
    }

    // --- 403 FORBIDDEN ---

    public function testCannotAccessOthersPrivateNote(): void
    {
        // Owner ID = 1
        $owner = new User('owner@example.com', 'hash');
        $this->setEntityId($owner, 1);

        // Attacker ID = 2
        $attacker = new User('attacker@example.com', 'hash');
        $this->setEntityId($attacker, 2);

        $privateNote = new Note($owner, 'Private', 'Secret', [], NoteVisibility::Private);
        // CRITICAL: Set ID for the note so Voter/Doctrine doesn't crash
        $this->setEntityId($privateNote, 123);

        $client = $this->createClientWithUser($attacker, [123 => $privateNote]);

        $client->request('GET', '/api/notes/123', server: [
            'HTTP_Authorization' => 'Bearer '.$this->createJwtForUser($attacker),
        ]);

        // Expectation: 403 Forbidden (mapped via ExceptionListener)
        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    // --- 400 BAD REQUEST (Validation & Types) ---

    public function testCreateNoteValidationFailsOnInvalidType(): void
    {
        $user = new User('user@example.com', 'hash');
        $this->setEntityId($user, 1); // Ensure user has ID
        $client = $this->createClientWithUser($user);

        $client->request(
            'POST',
            '/api/notes',
            server: [
                'HTTP_Authorization' => 'Bearer '.$this->createJwtForUser($user),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'title' => 'Test',
                'description' => 'Content',
                'labels' => 'INVALID_STRING_INSTEAD_OF_ARRAY',
            ])
        );

        // This should now return 400 (Invalid data types) handled by ExceptionListener
        self::assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testMalformedJsonReturns400(): void
    {
        $user = new User('user@example.com', 'hash');
        $client = $this->createClientWithUser($user);

        $client->request(
            'POST',
            '/api/notes',
            server: [
                'HTTP_Authorization' => 'Bearer '.$this->createJwtForUser($user),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '{"broken": json'
        );

        self::assertSame(400, $client->getResponse()->getStatusCode());
    }

    // --- HELPER TO SET PRIVATE ID ---
    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
