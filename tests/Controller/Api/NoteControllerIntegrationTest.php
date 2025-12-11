<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\DTO\Note\NoteSummaryDto;
use App\DTO\Note\NotesListResponseDto;
use App\DTO\Note\PaginationMetaDto;
use App\Entity\User;
use App\Query\Note\ListNotesQuery;
use App\Repository\UserRepository;
use App\Service\NotesQueryService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class NoteControllerIntegrationTest extends WebTestCase
{
    public function testListRequiresAuth(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/notes');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testListValidationErrorOnTooHighPerPage(): void
    {
        $user = new User('user@example.com', 'hash');
        $client = $this->createAuthenticatedClient($user);

        $client->request('GET', '/api/notes', [
            'per_page' => 150,
        ], server: [
            'HTTP_Authorization' => 'Bearer ' . $this->createJwtForUser($user, 'testsecret'),
        ]);

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Validation failed', $payload['error']);
        self::assertArrayHasKey('perPage', $payload['details']);
    }

    public function testListReturnsData(): void
    {
        $user = new User('user@example.com', 'hash');

        $notesService = $this->createStub(NotesQueryService::class);
        $notesService->method('listOwnedNotes')
            ->willReturn(new NotesListResponseDto(
                data: [
                    new NoteSummaryDto(
                        id: 1,
                        urlToken: 'uuid-1',
                        title: 'Title',
                        description: 'Description',
                        labels: ['work'],
                        visibility: 'private',
                        createdAt: new \DateTimeImmutable('2025-01-01T00:00:00+00:00'),
                        updatedAt: new \DateTimeImmutable('2025-01-02T00:00:00+00:00'),
                    ),
                ],
                meta: new PaginationMetaDto(page: 2, perPage: 5, total: 10),
            ));

        $client = $this->createAuthenticatedClient($user, $notesService);

        $client->request('GET', '/api/notes', [
            'page' => 2,
            'per_page' => 5,
            'q' => 'hello',
            'label' => ['work', 'dev'],
        ], server: [
            'HTTP_Authorization' => 'Bearer ' . $this->createJwtForUser($user),
        ]);

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $payload['data'][0]['id']);
        self::assertSame(['work'], $payload['data'][0]['labels']);
        self::assertSame(2, $payload['meta']['page']);
        self::assertSame(5, $payload['meta']['per_page']);
        self::assertSame(10, $payload['meta']['total']);
    }

    private function createAuthenticatedClient(User $user, ?NotesQueryService $notesQueryService = null)
    {
        $client = static::createClient();
        $container = static::getContainer();

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);
        $container->set(UserRepository::class, $userRepository);

        if ($notesQueryService !== null) {
            $container->set(NotesQueryService::class, $notesQueryService);
        }

        return $client;
    }

    private function createJwtForUser(User $user): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'test-jwt-secret';
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => $user->getUuid(),
            'exp' => time() + 3600,
        ], JSON_THROW_ON_ERROR));

        $signature = $this->base64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, $secret, true));

        return sprintf('%s.%s.%s', $header, $payload, $signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
