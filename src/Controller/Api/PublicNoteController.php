<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\Note\PublicNoteResponseDTO;
use App\Mapper\PublicNoteJsonMapper;
use App\Service\NoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/notes')]
final class PublicNoteController extends AbstractController
{
    public function __construct(
        private readonly NoteService $noteService,
        private readonly PublicNoteJsonMapper $mapper,
    ) {
    }

    #[Route('/{urlToken}', name: 'api_public_notes_get', methods: ['GET'])]
    public function getByToken(string $urlToken): JsonResponse
    {
        $note = $this->noteService->getPublicNoteByToken($urlToken);

        $dto = new PublicNoteResponseDTO(
            title: $note->getTitle(),
            description: $note->getDescription(),
            labels: $note->getLabels(),
            createdAt: $note->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );

        return new JsonResponse(['data' => $this->mapper->mapPublicNote($dto)], JsonResponse::HTTP_OK);
    }
}
