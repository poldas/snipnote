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

        self::assertDoesNotMatchRegularExpression(
            $forbiddenPattern, 
            $html, 
            sprintf("XSS vulnerability found! Payload: %s. Description: %s. Resulting HTML: %s", $payload, $description, $html)
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
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
            ],
            'Iframe with srcdoc' => [
                '<iframe srcdoc="<script>alert(1)</script>"></iframe>',
                '/srcdoc/i',
                'Iframe srcdoc is highly dangerous and should be stripped'
            ],
            'Button with formaction' => [
                '<button formaction="javascript:alert(1)">Click</button>',
                '/formaction/i',
                'Formaction can execute javascript'
            ],
            'Style with expression' => [
                '<div style="width: expression(alert(1));"></div>',
                '/expression/i',
                'CSS expressions should be removed'
            ],
            'Style with background url' => [
                '<div style="background-image: url(javascript:alert(1))"></div>',
                '/javascript/i',
                'Javascript in CSS urls should be blocked'
            ],
            'Data protocol in link' => [
                '[Click](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)',
                '/data:/i',
                'Data protocol can hide malicious scripts'
            ],
            'Double encoded entities' => [
                '<a href="&#38;#106;&#38;#97;&#38;#118;&#38;#97;&#38;#115;&#38;#99;&#38;#114;&#38;#105;&#38;#112;&#38;#116;&#38;#58;alert(1)">Click</a>',
                '/href/i',
                'Double encoded entities should not bypass protocol filters'
            ],
            'Tabindex focus XSS' => [
                '<div tabindex="1" onfocus="alert(1)">Focus me</div>',
                '/onfocus/i',
                'Focus events on focusable elements'
            ]
        ];
    }
}