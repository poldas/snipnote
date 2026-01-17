<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AuthPageController;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\EmailVerificationService;
use App\Service\PasswordResetService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class AuthPageControllerRateLimiterUnitTest extends TestCase
{
    private Container $container;

    private function createRateLimitMock(bool $accepted, int $remaining = 0): RateLimit
    {
        return new RateLimit($remaining, new \DateTimeImmutable(), $accepted, 10);
    }

    /**
     * @param array<string, object> $services
     */
    private function createController(array $services = []): AuthPageController
    {
        $csrfTokenManager = self::createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            fn (string $name, array $parameters = []) => '/'.$name.'?'.http_build_query($parameters)
        );

        $twig = self::createStub(Environment::class);
        $twig->method('render')->willReturn('rendered_content');

        $this->container = new Container();
        $this->container->set('router', $urlGenerator);
        $this->container->set('session', new Session(new MockArraySessionStorage()));
        $this->container->set('request_stack', new RequestStack());
        $this->container->set('twig', $twig);

        // Default stubs if not provided
        $authService = $services['authService'] ?? self::createStub(AuthService::class);
        $emailVerificationService = $services['emailVerificationService'] ?? self::createStub(EmailVerificationService::class);
        $passwordResetService = $services['passwordResetService'] ?? self::createStub(PasswordResetService::class);
        $userRepository = $services['userRepository'] ?? self::createStub(UserRepository::class);
        $emailResendLimiter = $services['emailResendLimiter'] ?? self::createStub(RateLimiterFactoryInterface::class);
        $forgotPasswordLimiter = $services['forgotPasswordLimiter'] ?? self::createStub(RateLimiterFactoryInterface::class);

        /** @var AuthService $authService */
        /** @var EmailVerificationService $emailVerificationService */
        /** @var PasswordResetService $passwordResetService */
        /** @var UserRepository $userRepository */
        /** @var RateLimiterFactoryInterface $emailResendLimiter */
        /** @var RateLimiterFactoryInterface $forgotPasswordLimiter */
        $controller = new AuthPageController(
            $authService,
            $emailVerificationService,
            $passwordResetService,
            $userRepository,
            $csrfTokenManager,
            $emailResendLimiter,
            $forgotPasswordLimiter
        );
        $controller->setContainer($this->container);

        return $controller;
    }

    public function testResendVerificationAllowed(): void
    {
        $email = 'test@example.com';
        $request = Request::create('/verify/email/resend', 'POST', ['email' => $email]);

        // Mock RateLimiter
        $limiter = self::createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($this->createRateLimitMock(true, 2));

        $limiterFactory = self::createStub(RateLimiterFactoryInterface::class);
        $limiterFactory->method('create')->willReturn($limiter);

        // Mock Service Expectation
        /** @var EmailVerificationService&MockObject $emailService */
        $emailService = self::createMock(EmailVerificationService::class);
        $emailService->expects(self::once())->method('sendForEmail')->with($email);

        $controller = $this->createController([
            'emailResendLimiter' => $limiterFactory,
            'emailVerificationService' => $emailService,
        ]);

        $this->container->get('request_stack')->push($request);
        $request->setSession($this->container->get('session'));

        $response = $controller->resendVerification($request);
        self::assertTrue($response->isRedirect('/app_verify_notice_page?state=resent&email=test%40example.com'));
    }

    public function testResendVerificationBlocked(): void
    {
        $email = 'test@example.com';
        $request = Request::create('/verify/email/resend', 'POST', ['email' => $email]);

        // Mock RateLimiter
        $limiter = self::createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($this->createRateLimitMock(false, 0));

        $limiterFactory = self::createStub(RateLimiterFactoryInterface::class);
        $limiterFactory->method('create')->willReturn($limiter);

        // Mock Service Expectation
        /** @var EmailVerificationService&MockObject $emailService */
        $emailService = self::createMock(EmailVerificationService::class);
        $emailService->expects(self::never())->method('sendForEmail');

        $controller = $this->createController([
            'emailResendLimiter' => $limiterFactory,
            'emailVerificationService' => $emailService,
        ]);

        $this->container->get('request_stack')->push($request);
        $request->setSession($this->container->get('session'));

        $response = $controller->resendVerification($request);
        self::assertTrue($response->isRedirect('/app_verify_notice_page?state=pending&email=test%40example.com'));

        $flashMessages = $this->container->get('session')->getFlashBag()->get('error');
        self::assertCount(1, $flashMessages);
        self::assertEquals('Zbyt wiele prób wysłania linku. Spróbuj ponownie później.', $flashMessages[0]);
    }

    public function testForgotPasswordAllowed(): void
    {
        $email = 'test@example.com';
        $request = Request::create('/forgot-password', 'POST', ['email' => $email]);

        // Mock RateLimiter
        $limiter = self::createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($this->createRateLimitMock(true, 4));

        $limiterFactory = self::createStub(RateLimiterFactoryInterface::class);
        $limiterFactory->method('create')->willReturn($limiter);

        // Mock Service Expectation
        /** @var PasswordResetService&MockObject $passwordService */
        $passwordService = self::createMock(PasswordResetService::class);
        $passwordService->expects(self::once())->method('requestPasswordReset')->with($email);

        $controller = $this->createController([
            'forgotPasswordLimiter' => $limiterFactory,
            'passwordResetService' => $passwordService,
        ]);

        $this->container->get('request_stack')->push($request);
        $request->setSession($this->container->get('session'));

        $response = $controller->forgotPassword($request);
        self::assertEquals(200, $response->getStatusCode());
    }

    public function testForgotPasswordBlocked(): void
    {
        $email = 'test@example.com';
        $request = Request::create('/forgot-password', 'POST', ['email' => $email]);

        // Mock RateLimiter
        $limiter = self::createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($this->createRateLimitMock(false, 0));

        $limiterFactory = self::createStub(RateLimiterFactoryInterface::class);
        $limiterFactory->method('create')->willReturn($limiter);

        // Mock Service Expectation
        /** @var PasswordResetService&MockObject $passwordService */
        $passwordService = self::createMock(PasswordResetService::class);
        $passwordService->expects(self::never())->method('requestPasswordReset');

        $controller = $this->createController([
            'forgotPasswordLimiter' => $limiterFactory,
            'passwordResetService' => $passwordService,
        ]);

        $this->container->get('request_stack')->push($request);
        $request->setSession($this->container->get('session'));

        $response = $controller->forgotPassword($request);
        self::assertEquals(200, $response->getStatusCode());

        $flashMessages = $this->container->get('session')->getFlashBag()->get('error');
        self::assertCount(1, $flashMessages);
        self::assertEquals('Zbyt wiele prób. Spróbuj ponownie później.', $flashMessages[0]);
    }
}
