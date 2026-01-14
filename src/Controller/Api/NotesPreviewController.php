<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Command\Note\GenerateMarkdownPreviewCommand;
use App\DTO\Note\NotesMarkdownPreviewRequestDto;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Service\MarkdownPreviewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/notes')]
final class NotesPreviewController extends AbstractController
{
    public function __construct(
        private readonly MarkdownPreviewService $previewService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/preview', name: 'api_notes_preview', methods: ['POST'])]
    public function preview(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $this->requireUser($user);
        $this->assertJsonContentType($request);
        $payload = $this->decodeJson($request);

        $description = (string) ($payload['description'] ?? '');
        if ('' === mb_trim($description)) {
            throw new ValidationException(['description' => ['This value should not be blank.']]);
        }

        $dto = new NotesMarkdownPreviewRequestDto(description: $description);

        $this->validate($dto);

        $command = new GenerateMarkdownPreviewCommand($dto->description);
        $response = $this->previewService->renderPreview($command);

        return new JsonResponse(['data' => ['html' => $response->html]], JsonResponse::HTTP_OK);
    }

    private function requireUser(?User $user): void
    {
        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentication required');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);

        if (null === $decoded && \JSON_ERROR_NONE !== json_last_error()) {
            throw new ValidationException(['_request' => ['Invalid JSON payload']]);
        }

        return \is_array($decoded) ? $decoded : [];
    }

    private function assertJsonContentType(Request $request): void
    {
        $contentType = $request->headers->get('Content-Type', '');
        if (!str_starts_with(mb_strtolower($contentType), 'application/json')) {
            throw new UnsupportedMediaTypeHttpException('Content-Type must be application/json');
        }
    }

    private function validate(object $command): void
    {
        $violations = $this->validator->validate($command);
        if (0 === $violations->count()) {
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
}
