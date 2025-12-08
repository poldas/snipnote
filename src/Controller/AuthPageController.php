<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthPageController extends AbstractController
{
    #[Route('/', name: 'app_landing', methods: ['GET'])]
    public function landing(): Response
    {
        return $this->render('auth/landing.html.twig', [
            'hero' => [
                'headline' => 'Notuj. Udostępniaj. Współpracuj w Snipnote.',
                'subcopy' => 'Szybkie, bezpieczne i przejrzyste notatki, które łatwo udostępnisz innym.',
            ],
            'navSwitch' => $this->navToLogin(),
        ]);
    }

    #[Route('/login', name: 'app_login_page', methods: ['GET'])]
    public function login(Request $request): Response
    {
        $redirect = $request->query->get('redirect');

        return $this->render('auth/login.html.twig', [
            'redirect' => $redirect,
            'hero' => [
                'headline' => 'Wróć do swoich notatek',
                'subcopy' => 'Zaloguj się, aby zarządzać notatkami i współpracować z zespołem.',
                'ctaPrimary' => ['href' => '/register', 'label' => 'Nie masz konta? Zarejestruj się'],
            ],
            'navSwitch' => $this->navToRegister(),
        ]);
    }

    #[Route('/register', name: 'app_register_page', methods: ['GET'])]
    public function register(): Response
    {
        return $this->render('auth/register.html.twig', [
            'hero' => [
                'headline' => 'Załóż konto w Snipnote',
                'subcopy' => 'Twórz notatki, zapraszaj współpracowników i dziel się publicznie.',
            ],
            'navSwitch' => $this->navToLogin(),
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgot_password_page', methods: ['GET'])]
    public function forgotPassword(): Response
    {
        return $this->render('auth/forgot_password.html.twig', [
            'hero' => [
                'headline' => 'Odzyskaj dostęp do konta',
                'subcopy' => 'Podaj email, a wyślemy instrukcje resetu hasła.',
            ],
            'navSwitch' => $this->navToLogin(),
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password_page', methods: ['GET'], defaults: ['token' => null])]
    public function resetPassword(?string $token = null): Response
    {
        return $this->render('auth/reset_password.html.twig', [
            'token' => $token,
            'hero' => [
                'headline' => 'Ustaw nowe hasło',
                'subcopy' => 'Skorzystaj z linku z maila, aby odzyskać dostęp do konta.',
            ],
            'navSwitch' => $this->navToLogin(),
        ]);
    }

    #[Route('/verify/email/notice', name: 'app_verify_notice_page', methods: ['GET'])]
    public function verifyNotice(Request $request): Response
    {
        $state = $request->query->get('state', 'pending');

        return $this->render('auth/verify_notice.html.twig', [
            'state' => $state,
            'navSwitch' => $this->navToLogin(),
            'hero' => [
                'headline' => 'Potwierdź swój adres email',
                'subcopy' => 'Kliknij w link aktywacyjny, aby dokończyć rejestrację.',
            ],
        ]);
    }

    private function navToLogin(): array
    {
        return ['href' => '/login', 'label' => 'Masz już konto? Zaloguj się'];
    }

    private function navToRegister(): array
    {
        return ['href' => '/register', 'label' => 'Nie masz konta? Zarejestruj się'];
    }
}
