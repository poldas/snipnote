<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Note;
use App\Entity\NoteVisibility;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PublicNotePageControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);
        
        self::ensureKernelShutdown();
    }

    public function testOwnerCanViewPrivateNotePreview(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $user = new User('owner@example.com', 'password');
        $em->persist($user);

        $note = new Note(
            $user,
            'Private Title',
            'Private Desc',
            [],
            NoteVisibility::Private
        );
        $note->setUrlToken('123e4567-e89b-12d3-a456-426614174000');
        $em->persist($note);
        $em->flush();

        $client->loginUser($user);

        $client->request('GET', '/n/123e4567-e89b-12d3-a456-426614174000');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Private Title');
    }

    public function testGuestCannotViewPrivateNotePreview(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $user = new User('owner@example.com', 'password');
        $em->persist($user);
        $note = new Note(
            $user,
            'Private Title',
            'Private Desc',
            [],
            NoteVisibility::Private
        );
        $note->setUrlToken('123e4567-e89b-12d3-a456-426614174000');
        $em->persist($note);
        $em->flush();

        $client->request('GET', '/n/123e4567-e89b-12d3-a456-426614174000');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDraftNoteIsAlwaysInaccessibleViaPublicUrl(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $user = new User('owner@example.com', 'password');
        $em->persist($user);
        $note = new Note(
            $user,
            'Draft Title',
            'Draft Desc',
            [],
            NoteVisibility::Draft
        );
        $note->setUrlToken('123e4567-e89b-12d3-a456-426614174009');
        $em->persist($note);
        $em->flush();

        // 1. Check as Guest
        $client->request('GET', '/n/123e4567-e89b-12d3-a456-426614174009');
        $this->assertResponseStatusCodeSame(404);

        // 2. Check as Owner
        $client->loginUser($user);
        $client->request('GET', '/n/123e4567-e89b-12d3-a456-426614174009');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testRecipeViewIsRenderedForRecipeLabel(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $user = new User('chef@example.com', 'password');
        $em->persist($user);

        $note = new Note(
            $user,
            'Delicious Recipe',
            'Recipe Content',
            ['recipe', 'dinner'],
            NoteVisibility::Public
        );
        $note->setUrlToken('123e4567-e89b-12d3-a456-426614174999');
        $em->persist($note);
        $em->flush();

        $client->request('GET', '/n/123e4567-e89b-12d3-a456-426614174999');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('link[href*="recipe_view"]');
        $this->assertSelectorTextContains('h1', 'Delicious Recipe');
    }

    public function testTodoViewIsRenderedForTodoLabel(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $user = new User('planner@example.com', 'password');
        $em->persist($user);

        $note = new Note(
            $user,
            'Shopping List',
            'Buy milk',
            ['todo', 'urgent'],
            NoteVisibility::Public
        );
        $note->setUrlToken('123e4567-e89b-12d3-a456-426614175000');
        $em->persist($note);
        $em->flush();

        $client->request('GET', '/n/123e4567-e89b-12d3-a456-426614175000');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('link[href*="todo_view"]');
        $this->assertSelectorTextContains('h1', 'Shopping List');
    }
}
