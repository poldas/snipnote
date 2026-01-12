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
    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($em);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);
        
        self::ensureKernelShutdown();
    }

    public function testOwnerCanViewPrivateNotePreview(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = new User('owner@example.com', 'password');
        $em->persist($user);

        $note = new Note($user, 'Private Title', 'Private Desc', [], NoteVisibility::Private);
        $note->setUrlToken('11111111-1111-1111-1111-111111111111');
        $em->persist($note);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/n/11111111-1111-1111-1111-111111111111');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Private Title');
    }

    public function testGuestGets404ForPrivateNote(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = new User('owner@example.com', 'password');
        $em->persist($user);
        $note = new Note($user, 'Private Title', 'Private Desc', [], NoteVisibility::Private);
        $note->setUrlToken('22222222-2222-2222-2222-222222222222');
        $em->persist($note);
        $em->flush();

        $client->request('GET', '/n/22222222-2222-2222-2222-222222222222');

        $this->assertResponseStatusCodeSame(404);
        $this->assertSelectorTextContains('h2', 'Notatka niedostępna lub nieprawidłowy link');
    }

    public function testOtherUserGets403ForPrivateNoteMaskedAsNotFoundMessage(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $userA = new User('owner@example.com', 'password');
        $userB = new User('other@example.com', 'password');
        $em->persist($userA);
        $em->persist($userB);

        $note = new Note($userA, 'Private Title', 'Private Desc', [], NoteVisibility::Private);
        $note->setUrlToken('33333333-3333-3333-3333-333333333333');
        $em->persist($note);
        $em->flush();

        $client->loginUser($userB);
        $client->request('GET', '/n/33333333-3333-3333-3333-333333333333');

        // Security check: status is 403, but message is the same as 404
        $this->assertResponseStatusCodeSame(403);
        $this->assertSelectorTextContains('h2', 'Notatka niedostępna lub nieprawidłowy link');
    }

    public function testDraftNoteIsMaskedAs404EvenForOwner(): void
    {
        $client = self::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = new User('owner@example.com', 'password');
        $em->persist($user);
        $note = new Note($user, 'Draft Title', 'Draft Desc', [], NoteVisibility::Draft);
        $note->setUrlToken('44444444-4444-4444-4444-444444444444');
        $em->persist($note);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/n/44444444-4444-4444-4444-444444444444');

        $this->assertResponseStatusCodeSame(404);
    }
}
