<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Auth\RegisterRequestDTO;
use App\Exception\ValidationException;
use App\Service\AuthService;
use App\Service\EmailVerificationService;
use App\Service\PasswordResetService;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AuthPageController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly PasswordResetService $passwordResetService,
        private readonly UserRepository $userRepository,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
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

                    return $this->redirectToRoute('app_verify_notice_page', [
                        'state' => 'registered',
                        'email' => $email,
                    ]);
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
                    // Always show success message for security (enumeration protection)
                    $success = 'Jeśli konto istnieje, wysłaliśmy instrukcje resetu hasła.';
                    $this->passwordResetService->requestPasswordReset($email);
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

    #[Route('/reset-password/{token}', name: 'app_reset_password_page', methods: ['GET', 'POST'], defaults: ['token' => null])]
    public function resetPassword(Request $request, ?string $token = null): Response
    {
        if (!$token) {
            return $this->redirectToRoute('app_forgot_password_page');
        }

        $user = $this->passwordResetService->validateToken($token);
        $errors = [];

        if (!$user) {
            $errors[] = ['message' => 'Link wygasł lub jest nieprawidłowy. Poproś o nowy.'];
            // If token is invalid, we might want to disable the form or just show the error.
            // We pass null token to view to indicate issue or just handle via errors.
        }

        if ($user && $request->isMethod('POST')) {
            $submittedToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('reset_password', $submittedToken))) {
                $errors[] = ['message' => 'Nieprawidłowy token bezpieczeństwa. Spróbuj ponownie.'];
            } else {
                $password = (string) $request->request->get('password', '');
                $passwordConfirm = (string) $request->request->get('passwordConfirm', '');

                if (strlen($password) < 8) {
                    $errors[] = ['field' => 'password', 'message' => 'Hasło musi mieć min. 8 znaków.'];
                }
                if ($password !== $passwordConfirm) {
                    $errors[] = ['field' => 'passwordConfirm', 'message' => 'Hasła muszą być identyczne.'];
                }

                if ($errors === []) {
                    $this->passwordResetService->resetPassword($user, $password);
                    
                    // Redirect to login with success flash (simulated via query param or flash bag)
                    // Using query param for simplicity in this MVP context if flash bag not configured, 
                    // but AbstractController has addFlash.
                    $this->addFlash('success', 'Hasło zostało zmienione. Zaloguj się nowym hasłem.');
                    
                    return $this->redirectToRoute('app_login_page');
                }
            }
        }

        return $this->render('auth/reset_password.html.twig', [
            'token' => $token,
            'errors' => $errors,
            'action' => '/reset-password/' . $token,
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

    #[Route('/verify/email/resend', name: 'app_verify_email_resend', methods: ['POST'])]
    public function resendVerification(Request $request): Response
    {
        $submittedToken = (string) $request->request->get('_csrf_token', '');
        $email = (string) $request->request->get('email', '');
        $errors = [];

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('verify_email_resend', $submittedToken))) {
            $errors[] = ['message' => 'Nieprawidłowy token bezpieczeństwa. Spróbuj ponownie.'];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = ['field' => 'email', 'message' => 'Podaj poprawny adres email.'];
        }

        if ($errors === []) {
            $user = $this->userRepository->findOneByEmailCaseInsensitive($email);
            if ($user !== null && $user->isVerified()) {
                return $this->redirectToRoute('app_verify_notice_page', [
                    'state' => 'already_verified',
                    'email' => $email,
                ]);
            }

            $this->emailVerificationService->sendForEmail($email);

            return $this->redirectToRoute('app_verify_notice_page', [
                'state' => 'resent',
                'email' => $email,
            ]);
        }

        $viewData = $this->verifyNoticeView('pending', $email);
        $viewData['errors'] = $errors;

        return $this->render('auth/verify_notice.html.twig', $viewData);
    }

    #[Route('/verify/email/notice', name: 'app_verify_notice_page', methods: ['GET'])]
    public function verifyNotice(Request $request): Response
    {
        $state = (string) $request->query->get('state', 'pending');
        $email = (string) $request->query->get('email', '');

        return $this->render('auth/verify_notice.html.twig', $this->verifyNoticeView($state, $email));
    }

    private function verifyNoticeView(string $state, string $email): array
    {
        $state = $state ?: 'pending';

        $view = [
            'state' => $state,
            'prefill' => $email !== '' ? ['email' => $email] : [],
            'hero' => [
                'headline' => 'Potwierdź swój adres email',
                'subcopy' => 'Kliknij w link aktywacyjny, aby dokończyć rejestrację.',
            ],
            'message' => 'Wysłaliśmy link aktywacyjny na Twój email.',
            'steps' => [
                'Sprawdź skrzynkę oraz folder spam/oznaczone.',
                'Link jest jednorazowy i ma ograniczony czas ważności.',
                'Jeśli link nie działa, wyślij go ponownie poniżej.',
            ],
            'variant' => 'info',
            'navSwitch' => $this->navToLogin(),
            'pending' => false,
        ];

        return match ($state) {
            'registered' => [
                ...$view,
                'variant' => 'success',
                'message' => 'Dziękujemy za rejestrację! Wysłaliśmy link potwierdzający.',
                'hero' => [
                    'headline' => 'Potwierdź adres email i aktywuj konto',
                    'subcopy' => 'Sprawdź pocztę i kliknij link weryfikacyjny, aby zacząć korzystać ze Snipnote.',
                ],
                'steps' => [
                    'Otwórz wiadomość od Snipnote w swojej skrzynce.',
                    'Kliknij w link weryfikacyjny, aby aktywować konto.',
                    'Nie widzisz maila? Wyślij go ponownie lub sprawdź folder spam.',
                ],
            ],
            'resent' => [
                ...$view,
                'variant' => 'success',
                'message' => 'Nowy link weryfikacyjny został wysłany.',
                'hero' => [
                    'headline' => 'Nowy link w drodze',
                    'subcopy' => 'Sprawdź pocztę – wysłaliśmy kolejny email weryfikacyjny.',
                ],
                'steps' => [
                    'Użyj najnowszego linku z wiadomości.',
                    'Jeśli poprzedni wygasł, nowy zastępuje go w całości.',
                    'Maila nadal nie ma? Sprawdź folder spam/oznaczone.',
                ],
            ],
            'invalid' => [
                ...$view,
                'variant' => 'warning',
                'message' => 'Link wygasł lub jest nieprawidłowy.',
                'hero' => [
                    'headline' => 'Potrzebujesz nowego linku',
                    'subcopy' => 'Poproś o nową wiadomość, aby potwierdzić adres email.',
                ],
                'steps' => [
                    'Wyślij nowy link poniżej – zajmie to tylko chwilę.',
                    'Korzystaj z najnowszej wiadomości w skrzynce.',
                ],
            ],
            'already_verified' => [
                ...$view,
                'variant' => 'info',
                'message' => 'Konto jest już potwierdzone. Możesz się zalogować.',
                'hero' => [
                    'headline' => 'Email został już zweryfikowany',
                    'subcopy' => 'Przejdź do logowania, aby korzystać z notatek.',
                ],
                'steps' => [
                    'Przejdź do logowania i zaloguj się na swoje konto.',
                    'Jeśli chcesz, możesz poprosić o przypomnienie hasła.',
                ],
            ],
            default => $view,
        };
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