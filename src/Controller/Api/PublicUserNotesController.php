<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\Note\PublicNotesQueryDto;
use App\Exception\ValidationException;
use App\Mapper\PublicNoteJsonMapper;
use App\Service\PublicNotesCatalogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/public/users')]
final class PublicUserNotesController extends AbstractController
{
    public function __construct(
        private readonly PublicNotesCatalogService $catalogService,
        private readonly PublicNoteJsonMapper $mapper,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/{user_uuid}/notes', name: 'api_public_user_notes_list', methods: ['GET'])]
    public function list(Request $request, string $user_uuid): JsonResponse
    {
        $dto = new PublicNotesQueryDto(
            userUuid: $user_uuid,
            page: (int) $request->query->get('page', (string) PublicNotesQueryDto::DEFAULT_PAGE),
            perPage: (int) $request->query->get('per_page', (string) PublicNotesQueryDto::DEFAULT_PER_PAGE),
            searchQuery: $this->normalizeOptionalString($request->query->get('q')),
            labels: $this->extractLabels($request),
        );

        $this->validate($dto);

        $responseDto = $this->catalogService->getPublicNotes($dto);

        return new JsonResponse($this->mapper->mapResponse($responseDto), JsonResponse::HTTP_OK);
    }

    private function validate(object $command): void
    {
        $violations = $this->validator->validate($command);
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

    private function normalizeOptionalString(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<string>
     */
    private function extractLabels(Request $request): array
    {
        $candidates = [];

        $labelsArray = $request->query->all('labels');
        $candidates = array_merge($candidates, $labelsArray);

        $single = $request->query->get('label');
        if ($single !== null) {
            $candidates[] = $single;
        }

        $labels = [];
        foreach ($candidates as $candidate) {
            if (!\is_string($candidate)) {
                continue;
            }
            $normalized = $this->normalizeOptionalString($candidate);
            if ($normalized !== null) {
                $labels[] = $normalized;
            }
        }

        return array_values(array_unique($labels));
    }
}
