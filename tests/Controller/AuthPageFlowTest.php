<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AuthPageFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->userRepository = static::getContainer()->get(UserRepository::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        // Clean up database before each test
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    // --- REGISTRATION TESTS ---

    public function testRegisterAndVerifyEmailFlow(): void
    {
        // 1. Visit Register Page
        $this->client->request('GET', '/register');
        $this->assertResponseIsSuccessful();

        // 2. Submit Valid Form
        $this->client->submitForm('Załóż konto', [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'passwordConfirm' => 'password123',
        ]);

        // 3. Assert Redirect to Notice
        $this->assertResponseRedirects('/verify/email/notice?state=registered&email=newuser@example.com');
        
        // 4. Assert Email Sent (BEFORE redirecting)
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertEmailHeaderSame($email, 'To', 'newuser@example.com');
        
        $rawEmailContent = $email->getHtmlBody();
        preg_match('/href="([^"]+verify\/email[^"]+)"/', $rawEmailContent, $matches);
        $verifyUrl = $matches[1] ?? null;
        $this->assertNotNull($verifyUrl, 'Verification URL not found in email.');

        // 5. Follow Redirect to Notice Page
        $this->client->followRedirect();
        
        // 6. Visit Verification Link
        $this->client->request('GET', $verifyUrl);

        // 7. Assert Success Redirect to Login
        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();
        
        // 8. Assert Success Message and DB State
        $this->assertSelectorExists('[role="alert"]', 'Success flash should be visible');
        $this->assertSelectorTextContains('[role="alert"]', 'Adres email został potwierdzony');
        
        $user = $this->userRepository->findOneByEmailCaseInsensitive('newuser@example.com');
        $this->assertTrue($user->isVerified());
    }

    public function testRegisterValidationErrors(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Załóż konto', [
            'email' => 'invalid-email',
            'password' => 'short',
            'passwordConfirm' => 'mismatch',
        ]);

        $this->assertResponseIsSuccessful();
        // Just ensure we are still on the register page (title or headline)
        $this->assertSelectorTextContains('h1', 'Załóż konto');
    }

    public function testRegisterDuplicateEmail(): void
    {
        $this->createUser('existing@example.com', true);

        $this->client->request('GET', '/register');
        $this->client->submitForm('Załóż konto', [
            'email' => 'existing@example.com',
            'password' => 'password123',
            'passwordConfirm' => 'password123',
        ]);

        $this->assertResponseIsSuccessful();
        // Check for generic validation error text (Symfony default or translated)
        // Since we didn't add specific translation for UniqueEntity, it might be English or default Polish
        // Let's check if we are still on register page, implying failure
        $this->assertSelectorTextContains('h1', 'Załóż konto');
        // And maybe check if email value is preserved
        $this->assertInputValueSame('email', 'existing@example.com');
    }

    // --- LOGIN TESTS ---

    public function testLoginSuccess(): void
    {
        $this->createUser('login@example.com', true, 'password123');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Zaloguj się', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $this->assertResponseRedirects('/notes');
        $this->client->followRedirect();
        
        // Check for logout form/button presence
        $this->assertSelectorExists('form[action="/logout"]', 'Logout form should be visible');
    }

    public function testLoginFailureBadCredentials(): void
    {
        $this->createUser('login@example.com', true, 'password123');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Zaloguj się', [
            'email' => 'login@example.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertResponseRedirects('/login'); 
        $this->client->followRedirect();
        
        // We implemented Polish translation "Nieprawidłowy adres email lub hasło."
        $this->assertSelectorTextContains('body', 'Nieprawidłowy adres email lub hasło');
    }

    public function testLoginUnverifiedUser(): void
    {
        $this->createUser('unverified@example.com', false, 'password123');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Zaloguj się', [
            'email' => 'unverified@example.com',
            'password' => 'password123',
        ]);

        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();

        $this->assertSelectorTextContains('body', 'Musisz najpierw potwierdzić adres email');
    }

    public function testLoginCsrfProtection(): void
    {
        $this->client->request('GET', '/login');
        
        $this->client->request('POST', '/login', [
            'email' => 'any@example.com',
            'password' => 'any',
            '_csrf_token' => 'invalid_token',
        ]);

        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();
        // The message is translated to Polish: "Nieprawidłowy token CSRF."
        $this->assertSelectorTextContains('body', 'Nieprawidłowy token CSRF.');
    }

    // --- PASSWORD RESET TESTS ---

    public function testForgotPasswordForVerifiedUser(): void
    {
        $this->createUser('verified@example.com', true);

        $this->client->request('GET', '/forgot-password');
        $this->client->submitForm('Wyślij instrukcje', [
            'email' => 'verified@example.com',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Jeśli konto istnieje, wysłaliśmy instrukcje');

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertEmailHeaderSame($email, 'To', 'verified@example.com');
        $this->assertEmailHeaderSame($email, 'Subject', 'Zresetuj swoje hasło | Snipnote');
    }

    public function testForgotPasswordForUnverifiedUser(): void
    {
        $this->createUser('unverified@example.com', false);

        $this->client->request('GET', '/forgot-password');
        $this->client->submitForm('Wyślij instrukcje', [
            'email' => 'unverified@example.com',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertEmailHeaderSame($email, 'Subject', 'Potwierdź swój adres e-mail | Snipnote');
    }

    public function testResetPasswordFlow(): void
    {
        // 1. Setup User with Reset Token
        $user = $this->createUser('reset@example.com', true, 'oldpassword');
        $token = 'valid_reset_token';
        $user->setResetToken($token, new \DateTimeImmutable('+1 hour'));
        $this->entityManager->flush();

        // 2. Visit Reset Page
        $this->client->request('GET', '/reset-password/' . $token);
        $this->assertResponseIsSuccessful();

        // 3. Submit New Password
        $this->client->submitForm('Ustaw nowe hasło', [
            'password' => 'newpassword123',
            'passwordConfirm' => 'newpassword123',
        ]);

        // 4. Assert Redirect to Login with Success
        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('[role="alert"]', 'Hasło zostało zmienione');
        
        // 5. Verify Login with New Password works
        $this->client->submitForm('Zaloguj się', [
            'email' => 'reset@example.com',
            'password' => 'newpassword123',
        ]);
        $this->assertResponseRedirects('/notes');
    }

    public function testResetPasswordInvalidToken(): void
    {
        $this->client->request('GET', '/reset-password/invalid_token');
        
        // Current implementation renders the page with an error instead of redirecting
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[role="alert"]', 'Link wygasł lub jest nieprawidłowy');
    }

    // --- OTHER TESTS ---

    public function testLogout(): void
    {
        // Log in first
        $this->testLoginSuccess(); // Re-use helper or flow

        // Submit logout form
        $this->client->submitForm('Wyloguj'); // Assuming button text is "Wyloguj" inside the nav
        
        $this->assertResponseRedirects('/'); // Redirect to landing
        $this->client->followRedirect();
        
        // Assert we can see "Zaloguj się" link again
        $this->assertSelectorExists('a[href="/login"]');
    }

    public function testProtectedPageRedirectsToLogin(): void
    {
        $this->client->request('GET', '/notes');
        $this->assertResponseRedirects('/login');
    }

    public function testResendVerificationRedirectsOnInvalidData(): void
    {
        // 1. Request with invalid CSRF
        $this->client->request('POST', '/verify/email/resend', [
            'email' => 'any@example.com',
            '_csrf_token' => 'wrong',
        ]);

        // 2. Should redirect to notice page (PRG pattern)
        $this->assertResponseRedirects('/verify/email/notice?state=pending&email=any@example.com');
        
        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        
        // 3. Check for flash message error
        $this->assertSelectorTextContains('body', 'Nieprawidłowy token bezpieczeństwa');
    }
    
    // --- HELPERS ---

    private function createUser(string $email, bool $isVerified, string $plainPassword = 'password'): User
    {
        $user = new User(
            $email,
            'placeholder', // Will be overwritten
            null,
            $isVerified
        );
        
        // Hash password correctly
        $hashed = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPasswordHash($hashed);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}