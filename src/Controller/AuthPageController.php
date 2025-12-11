<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Auth\RegisterRequestDTO;
use App\Exception\ValidationException;
use App\Service\AuthService;
use App\Service\EmailVerificationService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AuthPageController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly MailerInterface $mailer,
        #[Autowire('%env(default::MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {}

    #[Route('/', name: 'app_landing', methods: ['GET'])]
    public function landing(): Response
    {
        if ($this->getUser()) {
            return $this->redirect('/notes');
        }

        return $this->render('auth/landing.html.twig', [
            'hero' => [
                'headline' => 'Notuj. Udostępniaj. Współpracuj w Snipnote.',
                'subcopy' => 'Szybkie, bezpieczne i przejrzyste notatki, które łatwo udostępnisz innym.',
            ],
            'navSwitch' => $this->navToLogin(),
        ]);
    }

    #[Route('/login', name: 'app_login_page', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirect('/notes');
        }

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

    #[Route('/register', name: 'app_register_page', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirect('/notes');
        }

        if ($request->isMethod('POST')) {
            $submittedToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('register', $submittedToken))) {
                return $this->render('auth/register.html.twig', [
                    'errors' => [['message' => 'Nieprawidłowy token bezpieczeństwa. Spróbuj ponownie.']],
                    'prefill' => ['email' => (string) $request->request->get('email', '')],
                    'pending' => false,
                    'navSwitch' => $this->navToLogin(),
                    'hero' => [
                        'headline' => 'Załóż konto w Snipnote',
                        'subcopy' => 'Twórz notatki, zapraszaj współpracowników i dziel się publicznie.',
                    ],
                ]);
            }

            $email = (string) $request->request->get('email', '');
            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('passwordConfirm', '');

            $errors = [];
            if ($password !== $passwordConfirm) {
                $errors[] = ['field' => 'passwordConfirm', 'message' => 'Hasła muszą być identyczne.'];
            }

            if ($errors === []) {
                try {
                    $dto = new RegisterRequestDTO($email, $password, false);
                    $this->authService->register($dto);
                    $this->emailVerificationService->sendForEmail($email);
                    $this->addFlash('success', 'Sprawdź skrzynkę – wysłaliśmy link aktywacyjny.');

                    return new RedirectResponse('/login');
                } catch (ValidationException $e) {
                    foreach ($e->getErrors() as $field => $messages) {
                        foreach ($messages as $msg) {
                            $errors[] = ['field' => $field, 'message' => $msg];
                        }
                    }
                }
            }

            return $this->render('auth/register.html.twig', [
                'errors' => $errors,
                'prefill' => ['email' => $email],
                'pending' => false,
                'navSwitch' => $this->navToLogin(),
                'hero' => [
                    'headline' => 'Załóż konto w Snipnote',
                    'subcopy' => 'Twórz notatki, zapraszaj współpracowników i dziel się publicznie.',
                ],
            ]);
        }

        return $this->render('auth/register.html.twig', [
            'hero' => [
                'headline' => 'Załóż konto w Snipnote',
                'subcopy' => 'Twórz notatki, zapraszaj współpracowników i dziel się publicznie.',
            ],
            'navSwitch' => $this->navToLogin(),
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgot_password_page', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        $errors = [];
        $success = null;
        $prefill = [];

        if ($request->isMethod('POST')) {
            $submittedToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('forgot_password', $submittedToken))) {
                $errors[] = ['message' => 'Nieprawidłowy token bezpieczeństwa. Spróbuj ponownie.'];
            } else {
                $email = (string) $request->request->get('email', '');
                $prefill['email'] = $email;

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = ['field' => 'email', 'message' => 'Podaj poprawny adres email'];
                } else {
                    $success = 'Jeśli konto istnieje, wysłaliśmy instrukcje resetu hasła.';
                    $this->sendResetEmail($email);
                }
            }
        }

        return $this->render('auth/forgot_password.html.twig', [
            'errors' => $errors,
            'prefill' => $prefill,
            'pending' => false,
            'success' => $success,
            'hero' => [
                'headline' => 'Odzyskaj dostęp do konta',
                'subcopy' => 'Podaj email, a wyślemy instrukcje resetu hasła.',
            ],
            'navSwitch' => $this->navToLogin(),
        ]);
    }

    private function sendResetEmail(string $email): void
    {
        $message = (new Email())
            ->from(new Address($this->mailerFrom ?: 'no-reply@snipnote.local', 'SnipNote'))
            ->to($email)
            ->subject('Reset hasła')
            ->text("Jeśli to Ty zainicjowałeś reset, użyj linku z aplikacji (placeholder MVP).");

        try {
            $this->mailer->send($message);
        } catch (\Throwable) {
            // Fail silently to keep neutral response.
        }
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

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): void
    {
        throw new \LogicException('This should be intercepted by the firewall.');
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
