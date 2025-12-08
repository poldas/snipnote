<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\Auth\AuthTokensDTO;
use App\DTO\Auth\LoginRequestDTO;
use App\DTO\Auth\ResendVerifyRequestDTO;
use App\DTO\Auth\LogoutRequestDTO;
use App\DTO\Auth\RefreshTokenRequestDTO;
use App\DTO\Auth\RegisterRequestDTO;
use App\DTO\Auth\UserPublicDTO;
use App\Exception\ValidationException;
use App\Service\AuthService;
use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);

        $dto = new RegisterRequestDTO(
            email: (string) ($payload['email'] ?? ''),
            password: (string) ($payload['password'] ?? ''),
            acceptTerms: (bool) ($payload['accept_terms'] ?? false),
        );

        $this->validate($dto);

        $result = $this->authService->register($dto);
        $this->emailVerificationService->sendForEmail($dto->email);

        return new JsonResponse([
            'data' => [
                'user' => $this->userToArray($result['user']),
                'tokens' => $this->tokensToArray($result['tokens']),
                'message' => 'Verification email sent',
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);

        $dto = new LoginRequestDTO(
            email: (string) ($payload['email'] ?? ''),
            password: (string) ($payload['password'] ?? ''),
        );

        $this->validate($dto);

        $result = $this->authService->login($dto);

        return new JsonResponse([
            'data' => [
                'user' => $this->userToArray($result['user']),
                'tokens' => $this->tokensToArray($result['tokens']),
            ],
        ], JsonResponse::HTTP_OK);
    }

    #[Route('/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $dto = new RefreshTokenRequestDTO(
            refreshToken: (string) ($payload['refresh_token'] ?? ''),
        );

        $this->validate($dto);

        $result = $this->authService->refresh($dto);

        return new JsonResponse([
            'data' => [
                'tokens' => $this->tokensToArray($result['tokens']),
            ],
        ], JsonResponse::HTTP_OK);
    }

    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $dto = new LogoutRequestDTO(
            refreshToken: (string) ($payload['refresh_token'] ?? ''),
        );

        $this->validate($dto);

        $this->authService->logout($dto);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/verify/resend', name: 'api_auth_verify_resend', methods: ['POST'])]
    public function resendVerification(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $dto = new ResendVerifyRequestDTO(
            email: (string) ($payload['email'] ?? ''),
        );

        $this->validate($dto);

        $this->emailVerificationService->sendForEmail($dto->email);

        return new JsonResponse([
            'data' => [
                'message' => 'If the account exists and is unverified, a verification email was sent',
            ],
        ], JsonResponse::HTTP_OK);
    }

    #[Route('/verify/email', name: 'api_auth_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request): Response
    {
        $email = (string) $request->query->get('email', '');
        $signature = (string) $request->query->get('signature', '');
        $expires = (string) $request->query->get('expires', '');

        $this->emailVerificationService->handleVerification($email, $signature, $expires);

        // For browser clicks (text/html), redirect to login with a success flash.
        $accept = (string) $request->headers->get('accept', '');
        if (str_contains($accept, 'text/html')) {
            $this->addFlash('success', 'Adres email został potwierdzony. Zaloguj się.');

            return $this->redirectToRoute('app_login_page');
        }

        return new JsonResponse([
            'data' => [
                'message' => 'Email verified',
                'status' => 'verified',
            ],
        ]);
    }

    private function decodeJson(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidationException(['_request' => ['Invalid JSON payload']]);
        }

        return \is_array($decoded) ? $decoded : [];
    }

    private function validate(object $dto): void
    {
        $violations = $this->validator->validate($dto);
        if ($violations->count() === 0) {
            return;
        }

        $errors = [];
        /** @var ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $property = $violation->getPropertyPath();
            $errors[$property][] = $violation->getMessage();
        }

        throw new ValidationException($errors);
    }

    private function userToArray(UserPublicDTO $user): array
    {
        return [
            'uuid' => $user->uuid,
            'email' => $user->email,
            'is_verified' => $user->isVerified,
            'roles' => $user->roles,
        ];
    }

    private function tokensToArray(AuthTokensDTO $tokens): array
    {
        return [
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken,
            'expires_in' => $tokens->expiresIn,
        ];
    }
}
