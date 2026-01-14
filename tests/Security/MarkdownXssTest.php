<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Command\Note\GenerateMarkdownPreviewCommand;
use App\Service\MarkdownPreviewService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MarkdownXssTest extends KernelTestCase
{
    private MarkdownPreviewService $markdownService;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->markdownService = self::getContainer()->get(MarkdownPreviewService::class);
    }

    /**
     * @dataProvider xssPayloadProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('xssPayloadProvider')]
    public function testMarkdownRenderingSanitizesXss(string $payload, string $forbiddenPattern, string $description): void
    {
        $command = new GenerateMarkdownPreviewCommand($payload);
        $result = $this->markdownService->renderPreview($command);
        $html = $result->html;

        $this->assertDoesNotMatchRegularExpression(
            $forbiddenPattern, 
            $html, 
            sprintf("XSS vulnerability found! Payload: %s. Description: %s. Resulting HTML: %s", $payload, $description, $html)
        );
    }

    public static function xssPayloadProvider(): array
    {
        return [
            'Basic script tag' => [
                '<script>alert("XSS")</script>',
                '/<script/i',
                'Script tags should be removed entirely'
            ],
            'Image with onerror attribute (HTML injection)' => [
                '<img src="x" onerror="alert(1)">',
                '/onerror/i',
                'Event attributes in HTML tags should be stripped'
            ],
            'Javascript pseudo-protocol in link' => [
                '[Click me](javascript:alert(1))',
                '/javascript:/i',
                'Javascript protocol in links should be blocked'
            ],
            'Iframe with javascript' => [
                '<iframe src="javascript:alert(1)"></iframe>',
                '/javascript:/i',
                'Iframe src should not allow javascript protocol'
            ],
            'Link with onmouseover (HTML injection)' => [
                '<a href="#" onmouseover="alert(1)">Hover</a>',
                '/onmouseover/i',
                'Onmouseover attribute should be stripped'
            ],
            'SVG with script' => [
                '<svg onload="alert(1)"></svg>',
                '/<svg/i',
                'SVG tags should be removed'
            ],
            'Encoded javascript protocol' => [
                '[Click](java&#x73;cript:alert(1))',
                '/javascript:/i',
                'Encoded protocols should be blocked'
            ],
            'Iframe without dangerous attributes' => [
                '<iframe src="https://www.youtube.com/embed/xyz" width="500"></iframe>',
                '/javascript:/i',
                'Safe iframes should be preserved but checked for protocols'
            ],
            'Input tag should not have events' => [
                '<input type="text" onfocus="alert(1)">',
                '/onfocus/i',
                'Input tags are allowed for TODOs but must not have events'
            ]
        ];
    }
}