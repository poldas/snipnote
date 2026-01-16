<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\Note;
use App\Entity\NoteVisibility;
use App\Entity\User;
use App\Repository\NoteCollaboratorRepository;
use App\Repository\NoteRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ApiPermissionTest extends WebTestCase
{
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

    /**
     * @param array<int, Note>|null $mockedNotes
     */
    private function createClientWithUser(User $user, ?array $mockedNotes = null, bool $isCollaborator = false): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();
        $container = static::getContainer();

        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);
        $userRepository->method('findOneByEmailCaseInsensitive')->willReturn($user);
        $container->set(UserRepository::class, $userRepository);

        if (null !== $mockedNotes) {
            $noteRepository = self::createStub(NoteRepository::class);
            $noteRepository->method('find')->willReturnCallback(fn ($id) => $mockedNotes[$id] ?? null);
            $container->set(NoteRepository::class, $noteRepository);
        }

        $collabRepo = self::createStub(NoteCollaboratorRepository::class);
        $collabRepo->method('isCollaborator')->willReturn($isCollaborator);
        $container->set(NoteCollaboratorRepository::class, $collabRepo);

        return $client;
    }

    /**
     * Test: Collaborator CANNOT delete a note.
     * Only the owner should have this right.
     */
    public function testCollaboratorCannotDeleteNote(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $this->setEntityId($owner, 1);

        $collaborator = new User('collab@example.com', 'hash');
        $this->setEntityId($collaborator, 2);

        $note = new Note($owner, 'Shared Note', 'Content', [], NoteVisibility::Private);
        $this->setEntityId($note, 123);

        // Client logged as collaborator
        $client = $this->createClientWithUser($collaborator, [123 => $note], true);

        $client->request('DELETE', '/api/notes/123', server: [
            'HTTP_Authorization' => 'Bearer '.$this->createJwtForUser($collaborator),
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode(), 'Collaborator should not be able to delete');
    }

    /**
     * Test: Stranger CANNOT list collaborators of a private note.
     */
    public function testStrangerCannotListCollaborators(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $this->setEntityId($owner, 1);

        $stranger = new User('stranger@example.com', 'hash');
        $this->setEntityId($stranger, 3);

        $note = new Note($owner, 'Private Note', 'Content', [], NoteVisibility::Private);
        $this->setEntityId($note, 123);

        $client = $this->createClientWithUser($stranger, [123 => $note], false);

        $client->request('GET', '/api/notes/123/collaborators', server: [
            'HTTP_Authorization' => 'Bearer '.$this->createJwtForUser($stranger),
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    /**
     * Test: Collaborator CAN add other collaborators.
     * According to PRD US-08 and Business Rules point 4.
     */
    public function testCollaboratorCanAddOtherCollaborators(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $this->setEntityId($owner, 1);

        $collaborator = new User('collab@example.com', 'hash');
        $this->setEntityId($collaborator, 2);

        $note = new Note($owner, 'Shared Note', 'Content', [], NoteVisibility::Private);
        $this->setEntityId($note, 123);

        $client = $this->createClientWithUser($collaborator, [123 => $note], true);

        $client->request(
            'POST',
            '/api/notes/123/collaborators',
            server: [
                'HTTP_Authorization' => 'Bearer '.$this->createJwtForUser($collaborator),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['email' => 'new-friend@example.com'])
        );

        // Should NOT be 403
        self::assertNotSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
