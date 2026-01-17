<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\Note\PublicNotesQueryDto;
use App\Entity\Note;
use App\Entity\NoteVisibility;
use App\Entity\User;
use App\Service\PublicNotesCatalogService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PublicNotesCatalogServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private PublicNotesCatalogService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(PublicNotesCatalogService::class);

        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        unset($this->entityManager, $this->service);
    }

    public function testThrowsWhenUserNotFound(): void
    {
        self::expectException(NotFoundHttpException::class);

        $this->service->getPublicNotes(new PublicNotesQueryDto(
            userUuid: '550e8400-e29b-41d4-a716-446655440000',
        ));
    }

    public function testReturnsFilteredPublicNotesWithPaginationAndExcerpt(): void
    {
        $owner = $this->persistUser('owner@example.com');
        $otherUser = $this->persistUser('other@example.com');

        $longDescription = str_repeat('long text ', 30); // > 200 chars

        $newerNote = $this->persistNote(
            owner: $owner,
            title: 'Newest note',
            description: $longDescription,
            labels: ['demo', 'extra'],
            visibility: NoteVisibility::Public,
            createdAt: new \DateTimeImmutable('2025-01-02T12:00:00Z'),
            urlToken: '550e8400-e29b-41d4-a716-446655440010'
        );

        $olderNote = $this->persistNote(
            owner: $owner,
            title: 'Older note',
            description: 'long but shorter text match',
            labels: ['demo'],
            visibility: NoteVisibility::Public,
            createdAt: new \DateTimeImmutable('2025-01-01T12:00:00Z'),
            urlToken: '550e8400-e29b-41d4-a716-446655440011'
        );

        // Notes that should be excluded
        $this->persistNote(
            owner: $owner,
            title: 'Private note',
            description: 'private content',
            labels: ['demo'],
            visibility: NoteVisibility::Private,
            createdAt: new \DateTimeImmutable('2025-01-03T12:00:00Z'),
            urlToken: '550e8400-e29b-41d4-a716-446655440012'
        );

        $this->persistNote(
            owner: $otherUser,
            title: 'Foreign public',
            description: 'long text from other user',
            labels: ['demo'],
            visibility: NoteVisibility::Public,
            createdAt: new \DateTimeImmutable('2025-01-04T12:00:00Z'),
            urlToken: '550e8400-e29b-41d4-a716-446655440013'
        );

        $this->entityManager->clear();

        $response = $this->service->getPublicNotes(new PublicNotesQueryDto(
            userUuid: $owner->getUuid(),
            page: 1,
            perPage: 1,
            searchQuery: '  long ',
            labels: [' demo ', 'demo'],
        ));

        self::assertSame(1, $response->meta->page);
        self::assertSame(1, $response->meta->perPage);
        self::assertSame(2, $response->meta->totalItems);
        self::assertSame(2, $response->meta->totalPages);
        self::assertCount(1, $response->data);

        $item = $response->data[0];
        self::assertSame('Newest note', $item->title);
        self::assertSame(['demo', 'extra'], $item->labels);
        self::assertSame('550e8400-e29b-41d4-a716-446655440010', $item->urlToken);
        self::assertStringEndsWith('…', $item->descriptionExcerpt);
        self::assertLessThanOrEqual(200, mb_strlen($item->descriptionExcerpt));
    }

    private function persistUser(string $email): User
    {
        $user = new User($email, 'hash');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @param list<string> $labels
     */
    private function persistNote(
        User $owner,
        string $title,
        string $description,
        array $labels,
        NoteVisibility $visibility,
        \DateTimeImmutable $createdAt,
        string $urlToken,
    ): Note {
        $note = new Note($owner, $title, $description, labels: $labels, visibility: $visibility);
        $note->setUrlToken($urlToken);
        $this->setDateTime($note, 'createdAt', $createdAt);
        $this->setDateTime($note, 'updatedAt', $createdAt);

        $this->entityManager->persist($note);
        $this->entityManager->flush();

        return $note;
    }

    private function setDateTime(Note $note, string $property, \DateTimeImmutable $value): void
    {
        $ref = new \ReflectionProperty($note, $property);
        $ref->setAccessible(true);
        $ref->setValue($note, $value);
    }

    private function resetDatabase(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);
    }
}
