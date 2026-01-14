<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\UuidCollisionException;
use App\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ExceptionListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        $this->logger->error('API exception', ['exception' => $exception]);

        $response = match (true) {
            $exception instanceof ValidationException => new JsonResponse(
                ['error' => 'Validation failed', 'details' => $exception->getErrors()],
                JsonResponse::HTTP_BAD_REQUEST
            ),
            $exception instanceof AuthenticationException => new JsonResponse(
                ['error' => 'Unauthorized'],
                JsonResponse::HTTP_UNAUTHORIZED
            ),
            $exception instanceof AccessDeniedException => new JsonResponse(
                ['error' => 'Forbidden'],
                JsonResponse::HTTP_FORBIDDEN
            ),
            $exception instanceof NotFoundHttpException => new JsonResponse(
                ['error' => 'Not found'],
                JsonResponse::HTTP_NOT_FOUND
            ),
            $exception instanceof ConflictHttpException => new JsonResponse(
                ['error' => 'Conflict'],
                JsonResponse::HTTP_CONFLICT
            ),
            $exception instanceof UuidCollisionException => new JsonResponse(
                ['error' => 'UUID generation failed, please retry'],
                JsonResponse::HTTP_CONFLICT
            ),
            $exception instanceof UnsupportedMediaTypeHttpException => new JsonResponse(
                ['error' => 'Unsupported Media Type'],
                JsonResponse::HTTP_UNSUPPORTED_MEDIA_TYPE
            ),
            default => new JsonResponse(
                ['error' => 'Internal server error'],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            ),
        };

        $event->setResponse($response);
    }
}
