<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Query\Note\PublicNotesQuery;
use App\Repository\NoteRepository;
use App\Repository\UserRepository;
use App\Service\NotesSearchParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

final class PublicCatalogController extends AbstractController
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly NoteRepository $noteRepository,
        private readonly NotesSearchParser $searchParser,
    ) {
    }

    #[Route('/u/{uuid}', name: 'public_catalog_index', methods: ['GET', 'POST'])]
    public function index(string $uuid, Request $request, #[CurrentUser] ?User $viewer): Response
    {
        // 1. Validate UUID format to prevent SQL errors
        if (!Uuid::isValid($uuid)) {
            return $this->render('public/catalog/error.html.twig');
        }

        $owner = $this->userRepository->findOneBy(['uuid' => $uuid]);
        if (null === $owner) {
            return $this->render('public/catalog/error.html.twig');
        }

        // 2. Security: Bot Protection (POST + CSRF for search)
        $isAjax = 'XMLHttpRequest' === $request->headers->get('X-Requested-With');

        $q = '';
        if ($request->isMethod('POST')) {
            if (!$isAjax) {
                throw new BadRequestHttpException('Only AJAX POST allowed');
            }

            $csrfToken = $request->headers->get('X-CSRF-Token');
            if (!$this->isCsrfTokenValid('catalog_search', (string) $csrfToken)) {
                throw new BadRequestHttpException('Invalid CSRF token');
            }

            $q = (string) $request->request->get('q', '');
        }

        // Always check query params for 'q' to support deep linking and initial load
        if ('' === $q) {
            $q = $request->query->get('q', '');
        }

        $page = max(1, (int) $request->query->get('page', '1'));
        $isOwner = null !== $viewer && $viewer->getUuid() === $owner->getUuid();

        $parsed = $this->searchParser->parse($q);

        $query = new PublicNotesQuery(
            ownerId: (int) $owner->getId(),
            page: $page,
            perPage: self::PER_PAGE,
            search: $parsed['text'],
            labels: $parsed['labels'],
        );

        $result = $this->noteRepository->findForCatalog($owner, $viewer, $query);

        $viewData = [
            'owner' => [
                'uuid' => $owner->getUuid(),
                'name' => $this->extractName($owner->getEmail()),
                'email' => $owner->getEmail(),
            ],
            'isOwnerView' => $isOwner,
            'notes' => $result->items,
            'meta' => [
                'page' => $page,
                'perPage' => self::PER_PAGE,
                'total' => $result->total,
                'totalPages' => max(1, (int) ceil($result->total / self::PER_PAGE)),
            ],
            'q' => $q,
        ];

        if ($isAjax) {
            return $this->render('public/catalog/_list.html.twig', [
                'view' => $viewData,
            ]);
        }

        return $this->render('public/catalog/index.html.twig', [
            'view' => $viewData,
        ]);
    }

    private function extractName(string $email): string
    {
        return explode('@', $email)[0];
    }
}
