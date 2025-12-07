<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Command\Note\CreateNoteCommand;
use App\Command\Note\UpdateNoteCommand;
use App\DTO\Note\NoteResponseDTO;
use App\Entity\Note;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Service\NoteService;
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
        private readonly ValidatorInterface $validator,
    ) {}

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
}
