<?php

declare(strict_types=1);

namespace App\Controller\Dev;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for previewing error pages during development.
 * This class is only accessible in the 'dev' environment.
 */
final class ErrorPreviewController extends AbstractController
{
    public function preview(int $code): Response
    {
        return $this->render('public_note.html.twig', [
            'note' => null,
            'errorCode' => $code,
            'theme' => 'default',
        ]);
    }
}
