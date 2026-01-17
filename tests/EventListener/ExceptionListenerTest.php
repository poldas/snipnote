<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\ExceptionListener;
use App\Exception\UuidCollisionException;
use App\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ExceptionListenerTest extends TestCase
{
    private HttpKernelInterface $kernel;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->kernel = self::createStub(HttpKernelInterface::class);
        $this->logger = self::createMock(LoggerInterface::class);
    }

    /**
     * @return array<string, array{exception: \Throwable, expectedStatus: int, expectedError: string}>
     */
    public static function exceptionProvider(): array
    {
        return [
            'validation' => [
                'exception' => new ValidationException(['title' => ['bad']]),
                'expectedStatus' => 400,
                'expectedError' => 'Validation failed',
            ],
            'forbidden' => [
                'exception' => new AccessDeniedException(),
                'expectedStatus' => 403,
                'expectedError' => 'Forbidden',
            ],
            'not found' => [
                'exception' => new NotFoundHttpException(),
                'expectedStatus' => 404,
                'expectedError' => 'Not found',
            ],
            'uuid collision' => [
                'exception' => new UuidCollisionException(),
                'expectedStatus' => 409,
                'expectedError' => 'UUID generation failed, please retry',
            ],
            'conflict' => [
                'exception' => new ConflictHttpException(),
                'expectedStatus' => 409,
                'expectedError' => 'Conflict',
            ],
            'unsupported media type' => [
                'exception' => new UnsupportedMediaTypeHttpException(),
                'expectedStatus' => 415,
                'expectedError' => 'Unsupported Media Type',
            ],
            'internal' => [
                'exception' => new \RuntimeException('boom'),
                'expectedStatus' => 500,
                'expectedError' => 'Internal server error',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('exceptionProvider')]
    public function testMapsExceptions(\Throwable $exception, int $expectedStatus, string $expectedError): void
    {
        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('API exception', ['exception' => $exception]);

        $listener = new ExceptionListener($this->logger);
        $request = Request::create('/api/notes');

        $event = new ExceptionEvent(
            $this->kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        $listener->onKernelException($event);
        $response = $event->getResponse();

        self::assertNotNull($response);
        self::assertSame($expectedStatus, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($expectedError, $payload['error']);
    }

    public function testNonApiPathIsIgnored(): void
    {
        $this->logger
            ->expects(self::never())
            ->method('error');

        $listener = new ExceptionListener($this->logger);
        $request = Request::create('/web');

        $event = new ExceptionEvent(
            $this->kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new \RuntimeException('boom'),
        );

        $listener->onKernelException($event);

        self::assertNull($event->getResponse());
    }
}
