<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Command\Collaborator\AddCollaboratorCommand;
use App\Command\Collaborator\RemoveCollaboratorByEmailCommand;
use App\Command\Collaborator\RemoveCollaboratorByIdCommand;
use App\DTO\Collaborator\NoteCollaboratorDto;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Query\Collaborator\ListCollaboratorsQuery;
use App\Service\NoteCollaboratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/notes')]
final class NoteCollaboratorController extends AbstractController
{
    public function __construct(
        private readonly NoteCollaboratorService $service,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/{noteId<\d+>}/collaborators', name: 'api_notes_collaborators_add', methods: ['POST'])]
    public function add(int $noteId, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $requester = $this->requireUser($user);
        $payload = $this->decodeJson($request);

        $command = new AddCollaboratorCommand(
            noteId: $noteId,
            email: (string) ($payload['email'] ?? ''),
        );

        $this->validate($command);

        $dto = $this->service->addCollaborator($command, $requester);

        return new JsonResponse(['data' => $this->collaboratorToArray($dto)], JsonResponse::HTTP_CREATED);
    }

    #[Route('/{noteId<\d+>}/collaborators/{collaboratorId<\d+>}', name: 'api_notes_collaborators_remove_by_id', methods: ['DELETE'])]
    public function removeById(int $noteId, int $collaboratorId, #[CurrentUser] ?User $user): JsonResponse
    {
        $requester = $this->requireUser($user);

        $command = new RemoveCollaboratorByIdCommand(
            noteId: $noteId,
            collaboratorId: $collaboratorId,
        );

        $this->validate($command);

        $this->service->removeById($command, $requester);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/{noteId<\d+>}/collaborators', name: 'api_notes_collaborators_remove_by_email', methods: ['DELETE'])]
    public function removeByEmail(int $noteId, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $requester = $this->requireUser($user);
        $email = $request->query->get('email');

        if (!\is_string($email)) {
            throw new ValidationException(['email' => ['Email query parameter is required']]);
        }

        $command = new RemoveCollaboratorByEmailCommand(
            noteId: $noteId,
            email: $email,
        );

        $this->validate($command);

        $this->service->removeByEmail($command, $requester);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/{noteId<\d+>}/collaborators', name: 'api_notes_collaborators_list', methods: ['GET'])]
    public function list(int $noteId, #[CurrentUser] ?User $user): JsonResponse
    {
        $requester = $this->requireUser($user);

        $query = new ListCollaboratorsQuery(noteId: $noteId);
        $this->validate($query);

        $collection = $this->service->listForNote($query->noteId, $requester);

        return new JsonResponse([
            'data' => array_map(
                fn (NoteCollaboratorDto $collaborator): array => $this->collaboratorToArray($collaborator),
                $collection->collaborators
            ),
        ], JsonResponse::HTTP_OK);
    }

    private function requireUser(?User $user): User
    {
        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentication required');
        }

        return $user;
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

    /**
     * @return array{id: int, note_id: int, email: string, user_id: int|null, created_at: string}
     */
    private function collaboratorToArray(NoteCollaboratorDto $dto): array
    {
        return [
            'id' => $dto->id,
            'note_id' => $dto->noteId,
            'email' => $dto->email,
            'user_id' => $dto->userId,
            'created_at' => $dto->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
