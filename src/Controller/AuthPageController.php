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

    private function navToLogin(): array
    {
        return ['href' => '/login', 'label' => 'Masz już konto? Zaloguj się'];
    }

    private function navToRegister(): array
    {
        return ['href' => '/register', 'label' => 'Nie masz konta? Zarejestruj się'];
    }
}
