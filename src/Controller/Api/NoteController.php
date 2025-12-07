<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Command\Note\CreateNoteCommand;
use App\Command\Note\UpdateNoteCommand;
use App\DTO\Note\ListNotesQueryDto;
use App\DTO\Note\NoteResponseDTO;
use App\DTO\Note\NoteSummaryDto;
use App\Entity\Note;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Service\NoteService;
use App\Service\NotesQueryService;
use App\Query\Note\ListNotesQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/notes')]
final class NoteController extends AbstractController
{
    public function __construct(
        private readonly NoteService $noteService,
        private readonly NotesQueryService $notesQueryService,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('', name: 'api_notes_list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $requester = $this->requireUser($user);

        $dto = new ListNotesQueryDto(
            page: (int) $request->query->get('page', ListNotesQueryDto::DEFAULT_PAGE),
            perPage: (int) $request->query->get('per_page', ListNotesQueryDto::DEFAULT_PER_PAGE),
            q: $request->query->get('q'),
            labels: $this->extractLabels($request),
        );

        $this->validate($dto);

        $query = new ListNotesQuery(
            ownerId: $requester->getId() ?? 0,
            page: $dto->page,
            perPage: $dto->perPage,
            q: $dto->q,
            labels: array_values($dto->labels),
        );

        $response = $this->notesQueryService->listOwnedNotes($query);

        return new JsonResponse([
            'data' => array_map(fn(NoteSummaryDto $note): array => $this->noteSummaryToArray($note), $response->data),
            'meta' => [
                'page' => $response->meta->page,
                'per_page' => $response->meta->perPage,
                'total' => $response->meta->total,
            ],
        ], JsonResponse::HTTP_OK);
    }

    #[Route('', name: 'api_notes_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $requester = $this->requireUser($user);
        $payload = $this->decodeJson($request);

        $command = new CreateNoteCommand(
            title: (string) ($payload['title'] ?? ''),
            description: (string) ($payload['description'] ?? ''),
            labels: \is_array($payload['labels'] ?? null) ? $payload['labels'] : [],
            visibility: (string) ($payload['visibility'] ?? 'private'),
        );

        $this->validate($command);

        $note = $this->noteService->createNote($requester, $command);

        return new JsonResponse(['data' => $this->toDto($note)], JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'api_notes_get', methods: ['GET'])]
    public function getOne(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        $requester = $this->requireUser($user);
        $note = $this->noteService->getNoteById($id, $requester);

        return new JsonResponse(['data' => $this->toDto($note)], JsonResponse::HTTP_OK);
    }

    #[Route('/{id<\d+>}', name: 'api_notes_update', methods: ['PATCH'])]
    public function update(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $requester = $this->requireUser($user);
        $payload = $this->decodeJson($request);

        $command = new UpdateNoteCommand(
            title: array_key_exists('title', $payload) ? (string) $payload['title'] : null,
            description: array_key_exists('description', $payload) ? (string) $payload['description'] : null,
            labels: array_key_exists('labels', $payload) && \is_array($payload['labels']) ? $payload['labels'] : null,
            visibility: array_key_exists('visibility', $payload) ? (string) $payload['visibility'] : null,
        );

        $this->validate($command);

        $note = $this->noteService->updateNote($id, $command, $requester);

        return new JsonResponse(['data' => $this->toDto($note)], JsonResponse::HTTP_OK);
    }

    #[Route('/{id<\d+>}', name: 'api_notes_delete', methods: ['DELETE'])]
    public function delete(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        $requester = $this->requireUser($user);
        $this->noteService->deleteNote($id, $requester);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function requireUser(?User $user): User
    {
        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentication required');
        }

        return $user;
    }

    private function decodeJson(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidationException(['_request' => ['Invalid JSON payload']]);
        }

        return \is_array($decoded) ? $decoded : [];
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

    private function toDto(Note $note): NoteResponseDTO
    {
        return new NoteResponseDTO(
            id: $note->getId() ?? 0,
            ownerId: $note->getOwner()->getId() ?? 0,
            urlToken: $note->getUrlToken(),
            title: $note->getTitle(),
            description: $note->getDescription(),
            labels: $note->getLabels(),
            visibility: $note->getVisibility()->value,
            createdAt: $note->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $note->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return list<string>
     */
    private function extractLabels(Request $request): array
    {
        $all = $request->query->all();
        $raw = $all['label'] ?? $request->query->get('label');

        $labels = \is_array($raw) ? $raw : ($raw !== null ? [$raw] : []);

        return array_values(array_filter($labels, static fn($value): bool => \is_string($value) && $value !== ''));
    }

    private function noteSummaryToArray(NoteSummaryDto $note): array
    {
        return [
            'id' => $note->id,
            'url_token' => $note->urlToken,
            'title' => $note->title,
            'description' => $note->description,
            'labels' => $note->labels,
            'visibility' => $note->visibility,
            'created_at' => $note->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $note->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
