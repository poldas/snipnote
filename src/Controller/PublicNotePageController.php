<?php

declare(strict_types=1);

namespace App\Controller;

use App\Command\Note\GenerateMarkdownPreviewCommand;
use App\Entity\User;
use App\Security\Voter\NoteVoter;
use App\Service\MarkdownPreviewService;
use App\Service\NoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class PublicNotePageController extends AbstractController
{
    public function __construct(
        private readonly NoteService $noteService,
        private readonly MarkdownPreviewService $markdownPreviewService,
    ) {}

    #[Route('/n/{urlToken}', name: 'public_notes_show', methods: ['GET'])]
    public function show(string $urlToken, #[CurrentUser] ?User $user): Response
    {
        $errorCode = null;
        $notePayload = null;

        try {
            $note = $this->noteService->getNotePreview($urlToken, $user);

            $preview = $this->markdownPreviewService->renderPreview(
                new GenerateMarkdownPreviewCommand($note->getDescription())
            );

            $canEdit = $user instanceof User && $this->isGranted(NoteVoter::EDIT, $note);

            $notePayload = [
                'title' => $note->getTitle(),
                'descriptionHtml' => $preview->html,
                'labels' => array_values(array_filter($note->getLabels(), static fn(string $label): bool => trim($label) !== '')),
                'createdAt' => $note->getCreatedAt(),
                'canEdit' => $canEdit,
                'editUrl' => $canEdit ? '/notes/' . $note->getUrlToken() . '/edit' : null,
                'loginUrl' => $this->buildLoginUrl($note->getUrlToken()),
            ];
        } catch (NotFoundHttpException) {
            $errorCode = 404;
        } catch (AccessDeniedException) {
            $errorCode = 403;
        } catch (\Throwable) {
            $errorCode = 0;
        }

        $response = $this->render('public_note.html.twig', [
            'note' => $notePayload,
            'errorCode' => $errorCode,
        ]);

        if ($errorCode !== null && $errorCode !== 0) {
            $response->setStatusCode($errorCode);
        }

        return $response;
    }

    private function buildLoginUrl(string $urlToken): string
    {
        $redirectPath = $this->generateUrl('public_notes_show', ['urlToken' => $urlToken], UrlGeneratorInterface::ABSOLUTE_PATH);

        return '/login?redirect=' . rawurlencode($redirectPath);
    }
}
