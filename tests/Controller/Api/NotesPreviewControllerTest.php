<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Controller\Api\NotesPreviewController;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Service\MarkdownPreviewService;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\Validator\Validation;

final class NotesPreviewControllerTest extends TestCase
{
    private function buildController(): NotesPreviewController
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $environment = new Environment([]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $converter = new MarkdownConverter($environment);
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowElement('a', ['href', 'title', 'target'])
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height']);
        $sanitizer = new HtmlSanitizer($config);

        return new NotesPreviewController(
            new MarkdownPreviewService($converter, $sanitizer),
            $validator
        );
    }

    public function testPreviewReturnsRenderedHtml(): void
    {
        $controller = $this->buildController();

        $request = Request::create(
            '/api/notes/preview',
            'POST',
            content: json_encode(['description' => '**bold**'], JSON_THROW_ON_ERROR),
            server: ['CONTENT_TYPE' => 'application/json']
        );

        $response = $controller->preview($request, new User('user@example.com', 'hash'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('<p><strong>bold</strong></p>', trim($payload['data']['html']));
    }

    public function testPreviewValidatesInput(): void
    {
        $controller = $this->buildController();

        $request = Request::create(
            '/api/notes/preview',
            'POST',
            content: json_encode(['description' => ''], JSON_THROW_ON_ERROR),
            server: ['CONTENT_TYPE' => 'application/json']
        );

        $this->expectException(ValidationException::class);
        $controller->preview($request, new User('user@example.com', 'hash'));
    }

    public function testPreviewRejectsUnsupportedContentType(): void
    {
        $controller = $this->buildController();

        $request = Request::create(
            '/api/notes/preview',
            'POST',
            content: json_encode(['description' => '**bold**'], JSON_THROW_ON_ERROR),
            server: ['CONTENT_TYPE' => 'text/plain']
        );

        $this->expectException(UnsupportedMediaTypeHttpException::class);
        $controller->preview($request, new User('user@example.com', 'hash'));
    }

    public function testPreviewRejectsTooLongDescription(): void
    {
        $controller = $this->buildController();

        $tooLong = str_repeat('a', 10001);
        $request = Request::create(
            '/api/notes/preview',
            'POST',
            content: json_encode(['description' => $tooLong], JSON_THROW_ON_ERROR),
            server: ['CONTENT_TYPE' => 'application/json']
        );

        $this->expectException(ValidationException::class);
        $controller->preview($request, new User('user@example.com', 'hash'));
    }
}
