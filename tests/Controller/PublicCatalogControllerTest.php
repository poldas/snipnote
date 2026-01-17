<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Note;
use App\Entity\NoteVisibility;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class PublicCatalogControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        // Clear DB
        $this->em->createQuery('DELETE FROM App\Entity\Note')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();

        // Create User
        $this->owner = new User('owner@test.com', 'hash');
        $this->em->persist($this->owner);
        $this->em->flush();
    }

    public function testIndexPageLoads(): void
    {
        $this->client->request('GET', '/u/'.$this->owner->getUuid());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('title', 'Katalog: owner');
        // Guest view - banner should NOT be present
        self::assertSelectorNotExists('.bg-indigo-600.text-white');
    }

    public function testOwnerViewShowsBanner(): void
    {
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/u/'.$this->owner->getUuid());

        self::assertResponseIsSuccessful();
        // Owner view - banner SHOULD be present
        self::assertSelectorExists('.bg-indigo-600.text-white');
        self::assertSelectorTextContains('.bg-indigo-600.text-white', 'To jest podgląd Twojego profilu publicznego');
    }

    public function testInvalidUuidReturnsErrorView(): void
    {
        $this->client->request('GET', '/u/not-a-uuid');
        self::assertResponseIsSuccessful(); // We return error template with 200 OK status
        self::assertSelectorTextContains('.pn-error', 'Notatki niedostępne lub nieprawidłowy link');
    }

    public function testNonExistentUserReturnsErrorView(): void
    {
        $randomUuid = Uuid::v4()->toRfc4122();
        $this->client->request('GET', '/u/'.$randomUuid);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.pn-error', 'Notatki niedostępne lub nieprawidłowy link');
    }

    public function testSearchViaGetIsProcessed(): void
    {
        // 1. Create Note
        $note = new Note($this->owner, 'Deep Link Query', 'Content', [], NoteVisibility::Public);
        $note->setUrlToken(Uuid::v4()->toRfc4122());
        $this->em->persist($note);
        $this->em->flush();

        // 2. Request with GET ?q=Deep
        $crawler = $this->client->request('GET', '/u/'.$this->owner->getUuid(), ['q' => 'Deep']);

        self::assertResponseIsSuccessful();
        $inputValue = $crawler->filter('input[name="q"]')->attr('value');
        self::assertEquals('Deep', $inputValue);
        self::assertStringContainsString('Deep Link Query', $this->client->getResponse()->getContent());
    }

    public function testSearchSqlInjectionResistance(): void
    {
        // Attempt common SQLi pattern
        $payload = "' OR 1=1 --";
        $this->client->request('GET', '/u/'.$this->owner->getUuid(), ['q' => $payload]);

        self::assertResponseIsSuccessful();
        // Should show "No results" empty state, NOT all notes
        self::assertStringContainsString('Brak wyników wyszukiwania', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('Deep Link Query', $this->client->getResponse()->getContent());
    }

    public function testSearchXssResistance(): void
    {
        // Attempt XSS
        $payload = '<script>alert("xss")</script>';
        $crawler = $this->client->request('GET', '/u/'.$this->owner->getUuid(), ['q' => $payload]);

        self::assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();

        // The payload should be present in the source but ESCAPED
        // Twig escapes < to &lt;
        self::assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $content);
        // The raw script tag should NOT be present
        self::assertStringNotContainsString($payload, $content);
    }

    public function testAjaxRequestReturnsOnlyListFragment(): void
    {
        $this->client->request('GET', '/u/'.$this->owner->getUuid(), [], [], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        self::assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();

        // Should contain the grid but NOT the layout/footer/nav
        self::assertStringContainsString('id="catalog-grid"', $content);
        self::assertStringNotContainsString('<nav', $content);
        self::assertStringNotContainsString('<footer', $content);
    }

    public function testPostWithoutAjaxHeaderIsForbidden(): void
    {
        $this->client->request('POST', '/u/'.$this->owner->getUuid(), ['q' => 'test']);
        self::assertResponseStatusCodeSame(400);
    }

    public function testPostWithoutCsrfIsForbidden(): void
    {
        $this->client->request(
            'POST',
            '/u/'.$this->owner->getUuid(),
            ['q' => 'test'],
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        self::assertResponseStatusCodeSame(400);
    }
}
