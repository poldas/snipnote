<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationAccountEnumerationTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Clean up users before each test
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    public function testRegistrationWithNewEmailShowsSuccess(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Załóż konto', [
            'email' => 'new-user@example.com',
            'password' => 'Password123!',
            'passwordConfirm' => 'Password123!',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Sprawdź swoją skrzynkę email');
        self::assertSelectorTextContains('body', 'Dziękujemy za rejestrację');
    }

    public function testRegistrationWithExistingUnverifiedEmailShowsSameSuccess(): void
    {
        // 1. Create unverified user
        $user = new User('unverified@example.com', 'hash', null, false);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->persist($user);
        $em->flush();

        // 2. Try to register with same email
        $this->client->request('GET', '/register');
        $this->client->submitForm('Załóż konto', [
            'email' => 'unverified@example.com',
            'password' => 'NewPassword123!',
            'passwordConfirm' => 'NewPassword123!',
        ]);

        // 3. Should NOT see "Email jest już w użyciu"
        self::assertResponseRedirects();
        $this->client->followRedirect();

        // Should show the SAME success page as a new registration
        self::assertSelectorTextContains('h1', 'Sprawdź swoją skrzynkę email');
        self::assertSelectorTextContains('body', 'Dziękujemy za rejestrację');
    }

    public function testRegistrationWithExistingVerifiedEmailShowsSameSuccess(): void
    {
        // 1. Create verified user
        $user = new User('verified@example.com', 'hash', null, true);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->persist($user);
        $em->flush();

        // 2. Try to register with same email
        $this->client->request('GET', '/register');
        $this->client->submitForm('Załóż konto', [
            'email' => 'verified@example.com',
            'password' => 'NewPassword123!',
            'passwordConfirm' => 'NewPassword123!',
        ]);

        // 3. Should NOT see "Email jest już w użyciu"
        self::assertResponseRedirects();
        $this->client->followRedirect();

        // Should show the SAME success page as a new registration
        self::assertSelectorTextContains('h1', 'Sprawdź swoją skrzynkę email');
        self::assertSelectorTextContains('body', 'Dziękujemy za rejestrację');
    }
}
