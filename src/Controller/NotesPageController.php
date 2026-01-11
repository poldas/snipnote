<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Collaborator\NoteCollaboratorDto;
use App\DTO\Note\NoteSummaryDto;
use App\Entity\NoteVisibility;
use App\Entity\User;
use App\Service\NoteCollaboratorService;
use App\Service\NoteService;
use App\Query\Note\ListNotesQuery;
use App\Service\NotesQueryService;
use App\Service\NotesSearchParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class NotesPageController extends AbstractController
{
    private const PER_PAGE = 50;
    private const MAX_SEARCH_LENGTH = 200;
    private const ALLOWED_VISIBILITIES = [
        'owner',
        NoteVisibility::Public->value,
        NoteVisibility::Private->value,
        NoteVisibility::Draft->value,
        'shared',
    ];

    public function __construct(
        private readonly NotesQueryService $notesQueryService,
        private readonly NotesSearchParser $notesSearchParser,
        private readonly NoteService $noteService,
        private readonly NoteCollaboratorService $noteCollaboratorService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Route('/notes/new', name: 'notes_new', methods: ['GET'])]
    public function new(#[CurrentUser] ?User $user): Response
    {
        if (!$user instanceof User) {
            return $this->redirect('/login?redirect=' . rawurlencode('/notes/new'));
        }

        return $this->render('notes/new.html.twig', [
            'csrfToken' => $this->generateCsrfToken('note_new'),
        ]);
    }

    #[Route('/notes/{id<\d+>}/edit', name: 'notes_edit', methods: ['GET'])]
    public function edit(int $id, Request $request, #[CurrentUser] ?User $user): Response
    {
        if (!$user instanceof User) {
            $redirectTo = $request->getPathInfo();
            $query = $request->getQueryString();
            if ($query) {
                $redirectTo .= '?' . $query;
            }

            return $this->redirect('/login?redirect=' . rawurlencode($redirectTo));
        }

        try {
            $note = $this->noteService->getNoteById($id, $user);
            $collaborators = $this->noteCollaboratorService->listForNote($id, $user);
        } catch (NotFoundHttpException) {
            return $this->render('notes/edit.html.twig', [
                'view' => [
                    'error' => 'Notatka niedostępna lub została usunięta.',
                    'dashboardUrl' => '/notes',
                ],
            ], new Response('', Response::HTTP_NOT_FOUND));
        } catch (AccessDeniedException) {
            return $this->render('notes/edit.html.twig', [
                'view' => [
                    'error' => 'Brak dostępu do tej notatki.',
                    'dashboardUrl' => '/notes',
                ],
            ], new Response('', Response::HTTP_FORBIDDEN));
        }

        $isOwner = $note->getOwner() === $user;

        $collaboratorsView = array_map(
            function (NoteCollaboratorDto $dto) use ($note, $user): array {
                $isSelf = $dto->email === $user->getUserIdentifier();

                return [
                    'id' => $dto->id,
                    'email' => $dto->email,
                    'isOwner' => false,
                    'isSelf' => $isSelf,
                    'userId' => $dto->userId,
                    'removeUrl' => '/api/notes/' . $note->getId() . '/collaborators/' . $dto->id,
                ];
            },
            $collaborators->collaborators
        );

        // Prepend owner row for display purposes
        array_unshift($collaboratorsView, [
            'id' => null,
            'email' => $note->getOwner()->getUserIdentifier(),
            'isOwner' => true,
            'isSelf' => $isOwner,
            'userId' => $note->getOwner()->getId(),
            'removeUrl' => null,
        ]);

        $view = [
            'noteId' => $note->getId(),
            'csrfToken' => $this->generateCsrfToken('note_edit'),
            'initialTitle' => $note->getTitle(),
            'initialDescription' => $note->getDescription(),
            'initialLabels' => $note->getLabels(),
            'initialVisibility' => $note->getVisibility()->value,
            'urlToken' => $note->getUrlToken(),
            'isOwner' => $isOwner,
            'canEdit' => true,
            'deleteUrl' => '/api/notes/' . $note->getId(),
            'patchUrl' => '/api/notes/' . $note->getId(),
            'regenerateUrl' => null,
            'dashboardUrl' => '/notes',
            // Dodano absolutny URL bazujący na bieżącym żądaniu
            'publicUrl' => $request->getSchemeAndHttpHost() . '/n/' . $note->getUrlToken(),
            'collaborators' => $collaboratorsView,
            'currentUserEmail' => $user->getUserIdentifier(),
        ];

        return $this->render('notes/edit.html.twig', [
            'view' => $view,
        ]);
    }

    #[Route('/notes', name: 'notes_dashboard', methods: ['GET'])]
    public function dashboard(Request $request, #[CurrentUser] ?User $user): Response
    {
        if (!$user instanceof User) {
            $redirectTo = $request->getPathInfo();
            $query = $request->getQueryString();
            if ($query) {
                $redirectTo .= '?' . $query;
            }

            return $this->redirect('/login?redirect=' . rawurlencode($redirectTo));
        }

        $rawQ = (string) $request->query->get('q', '');
        $q = $this->sanitizeSearch($rawQ);
        $page = max(1, (int) $request->query->get('page', 1));
        $visibility = $this->normalizeVisibility($request->query->get('visibility'));

        $parsed = $this->notesSearchParser->parse($q);

        $notes = [];
        $meta = [
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => 0,
            'totalPages' => 1,
        ];
        $error = null;

        try {
            $response = $this->notesQueryService->listOwnedNotes(
                new ListNotesQuery(
                    ownerId: $user->getId() ?? 0,
                    page: $page,
                    perPage: self::PER_PAGE,
                    q: $parsed['text'],
                    labels: $parsed['labels'],
                    visibility: $visibility,
                    ownerEmail: $user->getUserIdentifier(),
                )
            );

            $isSharedView = $visibility === 'shared';
            $notes = array_map(
                fn(NoteSummaryDto $note): array => $this->mapNoteSummary($note, $isSharedView),
                $response->data
            );

            $meta = [
                'page' => $response->meta->page,
                'perPage' => $response->meta->perPage,
                'total' => $response->meta->total,
                'totalPages' => max(1, (int) ceil($response->meta->total / max(1, $response->meta->perPage))),
            ];
        } catch (\Throwable) {
            $error = 'Nie udało się pobrać listy notatek. Spróbuj ponownie.';
        }

        return $this->render('notes/dashboard.html.twig', [
            'view' => [
                'q' => $rawQ,
                'parsedQ' => $parsed,
                'visibility' => $visibility,
                'notes' => $notes,
                'meta' => $meta,
                'isLoading' => false,
                'error' => $error,
            ],
        ]);
    }

    private function normalizeVisibility(mixed $raw): string
    {
        if (!is_string($raw)) {
            return 'owner';
        }

        $value = strtolower(trim($raw));
        return in_array($value, self::ALLOWED_VISIBILITIES, true) ? $value : 'owner';
    }

    private function sanitizeSearch(string $q): string
    {
        $trimmed = trim($q);
        if (mb_strlen($trimmed) > self::MAX_SEARCH_LENGTH) {
            return mb_substr($trimmed, 0, self::MAX_SEARCH_LENGTH);
        }

        return $trimmed;
    }

    /**
     * @return array{
     *     id: int,
     *     urlToken: string,
     *     title: string,
     *     excerpt: string,
     *     labels: list<string>,
     *     visibility: string,
     *     isShared: bool,
     *     createdAt: \DateTimeImmutable,
     *     updatedAt: \DateTimeImmutable,
     *     editUrl: string,
     *     deleteUrl: string,
     *     publicUrl: string
     * }
     */
    private function mapNoteSummary(NoteSummaryDto $note, bool $isSharedView = false): array
    {
        $labels = array_values(array_filter($note->labels, static fn(string $label): bool => trim($label) !== ''));
        $excerpt = $this->makeExcerpt($note->description);

        return [
            'id' => $note->id,
            'urlToken' => $note->urlToken,
            'title' => $note->title,
            'excerpt' => $excerpt,
            'labels' => $labels,
            'visibility' => $note->visibility,
            'isShared' => $isSharedView,
            'createdAt' => $note->createdAt,
            'updatedAt' => $note->updatedAt,
            'editUrl' => '/notes/' . $note->id . '/edit',
            'deleteUrl' => '/api/notes/' . $note->id,
            'publicUrl' => '/n/' . $note->urlToken,
        ];
    }

    private function makeExcerpt(string $description): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $description) ?? '');
        if ($normalized === '') {
            return '(Brak opisu)';
        }

        if (mb_strlen($normalized) <= 255) {
            return $normalized;
        }

        return rtrim(mb_substr($normalized, 0, 252)) . '...';
    }

    private function generateCsrfToken(string $tokenId): string
    {
        return $this->csrfTokenManager->getToken($tokenId)->getValue();
    }
}
