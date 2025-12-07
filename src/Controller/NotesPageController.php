<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Note\NoteSummaryDto;
use App\Entity\User;
use App\Query\Note\ListNotesQuery;
use App\Service\NotesQueryService;
use App\Service\NotesSearchParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class NotesPageController extends AbstractController
{
    private const PER_PAGE = 10;
    private const MAX_SEARCH_LENGTH = 200;

    public function __construct(
        private readonly NotesQueryService $notesQueryService,
        private readonly NotesSearchParser $notesSearchParser,
    ) {}

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
                )
            );

            $notes = array_map(
                fn(NoteSummaryDto $note): array => $this->mapNoteSummary($note),
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
                'notes' => $notes,
                'meta' => $meta,
                'isLoading' => false,
                'error' => $error,
            ],
        ]);
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
     *     createdAt: \DateTimeImmutable,
     *     updatedAt: \DateTimeImmutable,
     *     editUrl: string,
     *     deleteUrl: string,
     *     publicUrl: string
     * }
     */
    private function mapNoteSummary(NoteSummaryDto $note): array
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
}
