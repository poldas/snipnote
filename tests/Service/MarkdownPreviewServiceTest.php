<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Command\Note\GenerateMarkdownPreviewCommand;
use App\Service\MarkdownPreviewService;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class MarkdownPreviewServiceTest extends TestCase
{
    public function testRenderPreviewEscapesAndConvertsMarkdown(): void
    {
        $environment = new Environment([]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $converter = new MarkdownConverter($environment);
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowElement('a', ['href', 'title'])
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height']);
        $sanitizer = new HtmlSanitizer($config);
        $service = new MarkdownPreviewService($converter, $sanitizer);

        $command = new GenerateMarkdownPreviewCommand("Hello **world**\n\n<script>alert(1)</script>");
        $response = $service->renderPreview($command);

        self::assertStringContainsString('<strong>world</strong>', $response->html);
        self::assertStringNotContainsString('<script>', $response->html);
        self::assertStringContainsString('<p>Hello <strong>world</strong></p>', $response->html);
    }
}
