<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Command\Note\GenerateMarkdownPreviewCommand;
use App\Service\MarkdownPreviewService;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\Attributes\DataProvider;
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
            ->allowElement('a', ['href', 'title', 'target'])
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height']);
        $sanitizer = new HtmlSanitizer($config);
        $service = new MarkdownPreviewService($converter, $sanitizer);

        $command = new GenerateMarkdownPreviewCommand("Hello **world**\n\n<script>alert(1)</script>");
        $response = $service->renderPreview($command);

        self::assertStringContainsString('<strong>world</strong>', $response->html);
        self::assertStringNotContainsString('<script>', $response->html);
        self::assertStringContainsString('<p>Hello <strong>world</strong></p>', $response->html);
    }

    #[DataProvider('dangerousPayloadProvider')]
    public function testRenderPreviewSanitizesDangerousPayloads(string $markdown, string $unexpected, ?string $expected = null): void
    {
        $environment = new Environment([]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $converter = new MarkdownConverter($environment);

        // Replicating the config from the main test to ensure consistency in what we are testing
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowElement('a', ['href', 'title', 'target'])
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height']);
        $sanitizer = new HtmlSanitizer($config);
        $service = new MarkdownPreviewService($converter, $sanitizer);

        $command = new GenerateMarkdownPreviewCommand($markdown);
        $response = $service->renderPreview($command);

        self::assertStringNotContainsString($unexpected, $response->html, "Security check failed: Found unexpected '$unexpected' in output.");

        if ($expected) {
            self::assertStringContainsString($expected, $response->html);
        }
    }

    public static function dangerousPayloadProvider(): \Generator
    {
        yield 'svg_injection' => [
            '<svg><script>alert(1)</script></svg>',
            '<script>',
        ];

        yield 'script_tag' => [
            '<script>alert(1)</script>',
            '<script>'
        ];

        yield 'img_onerror' => [
            '<img src="http://example.com/x.jpg" onerror=alert(1)>',
            'onerror',
            '<img src="http://example.com/x.jpg"' // Basic validation that img tag remains but attribute is gone
        ];

        yield 'javascript_link' => [
            '[Click me](javascript:alert(1))',
            'javascript:',
            '<a>Click me</a>' // Sanitizer typically strips the href if invalid scheme, leaving the tag or stripping the tag depending on config. 
            // With current config, it might strip the href attribute.
        ];

        yield 'data_uri_link' => [
            '[Link](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)',
            'data:',
        ];

        yield 'iframe_injection' => [
            '<iframe src="https://malicious.com"></iframe>',
            '<iframe'
        ];

        yield 'object_injection' => [
            '<object data="something"></object>',
            '<object'
        ];

        yield 'embed_injection' => [
            '<embed src="something">',
            '<embed'
        ];

        yield 'style_tag' => [
            '<style>body { display: none; }</style>',
            '<style>'
        ];

        yield 'form_injection' => [
            '<form action="https://evil.com"><input type="submit"></form>',
            '<form'
        ];

        yield 'onclick_attribute' => [
            '<a href="http://example.com" onclick="stealCookies()">Link</a>',
            'onclick',
            '<a href="http://example.com">Link</a>'
        ];
    }
}
