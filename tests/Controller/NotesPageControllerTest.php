<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Note;
use App\Entity\NoteCollaborator;
use App\Entity\NoteVisibility;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NotesPageControllerTest extends WebTestCase
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

    public function testGuestIsRedirectedToLogin(): void
    {
        $client = self::createClient();

        $urls = ['/notes', '/notes/new', '/notes/1/edit'];

        foreach ($urls as $url) {
            $client->request('GET', $url);
            // Symfony firewall redirects to /login by default because of access_control in security.yaml
            self::assertResponseRedirects('http://localhost/login');
        }
    }

    public function testDashboardIsAccessibleForLoggedInUser(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = new User('user@example.com', 'password');
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/notes');

        self::assertResponseIsSuccessful();
        // The dashboard doesn't have an H1 with "Twoje notatki", but the title contains it
        self::assertPageTitleContains('Twoje notatki');
        self::assertSelectorTextContains('.text-slate-600', 'Znalezionych notatek');
    }

    public function testNewNoteFormIsAccessible(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = new User('user@example.com', 'password');
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/notes/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name="title"]');
    }

    public function testEditNoteFormIsAccessibleForOwner(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = new User('owner@example.com', 'password');
        $em->persist($user);

        $note = new Note($user, 'Owner Note', 'Description', [], NoteVisibility::Private);
        $note->setUrlToken('123e4567-e89b-12d3-a456-426614174000');
        $em->persist($note);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/notes/'.$note->getId().'/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Edytuj notatkę');
        self::assertSelectorExists('div[data-controller="edit-note"]');
    }

    public function testEditNonExistentNoteReturns404(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = new User('user@example.com', 'password');
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/notes/99999/edit');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('body', 'Notatka niedostępna lub została usunięta.');
    }

    public function testEditNoteWithoutAccessReturns403(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = new User('owner@example.com', 'password');
        $otherUser = new User('other@example.com', 'password');
        $em->persist($owner);
        $em->persist($otherUser);

        $note = new Note($owner, 'Private Note', 'Description', [], NoteVisibility::Private);
        $em->persist($note);
        $em->flush();

        $client->loginUser($otherUser);
        $client->request('GET', '/notes/'.$note->getId().'/edit');

        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('body', 'Brak dostępu do tej notatki.');
    }

    public function testDashboardFiltersWork(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = new User('user@example.com', 'password');
        $em->persist($user);

        $note1 = new Note($user, 'Public Note', 'Desc', [], NoteVisibility::Public);
        $note1->setUrlToken('123e4567-e89b-12d3-a456-426614174001');
        $note2 = new Note($user, 'Private Note', 'Desc', [], NoteVisibility::Private);
        $note2->setUrlToken('123e4567-e89b-12d3-a456-426614174002');

        $em->persist($note1);
        $em->persist($note2);
        $em->flush();

        $client->loginUser($user);

        // Filter by public
        $client->request('GET', '/notes?visibility=public');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.bg-indigo-100', '1');
        self::assertSelectorTextContains('body', 'Public Note');
        self::assertSelectorTextNotContains('body', 'Private Note');
    }

    public function testEditNoteShowsCollaborators(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $owner = new User('owner@example.com', 'password');
        $collabUser = new User('collab@example.com', 'password');
        $em->persist($owner);
        $em->persist($collabUser);

        $note = new Note($owner, 'Shared Note', 'Description', [], NoteVisibility::Private);
        $note->setUrlToken('123e4567-e89b-12d3-a456-426614174003');
        $em->persist($note);

        $collaborator = new NoteCollaborator($note, $collabUser->getEmail(), $collabUser);
        $em->persist($collaborator);
        $em->flush();

        $client->loginUser($owner);
        $client->request('GET', '/notes/'.$note->getId().'/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'collab@example.com');
        self::assertSelectorTextContains('body', 'owner@example.com');
    }
}
