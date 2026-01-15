<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Command\Note\GenerateMarkdownPreviewCommand;
use App\Service\MarkdownPreviewService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MarkdownPreviewServiceIntegrationTest extends KernelTestCase
{
    private MarkdownPreviewService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(MarkdownPreviewService::class);
    }

    public function testRendersAdvancedMarkdownElements(): void
    {
        $markdown = <<<MARKDOWN
# Title 1
[Link to Title](#title-1)

| Table | Header |
| :--- | :--- |
| Cell 1 | Cell 2 |

![Image Alt](https://example.com/img.png)

```javascript
function hello() {
  console.log("world");
}
```

- [x] Done task
- [ ] Todo task
MARKDOWN;

        $command = new GenerateMarkdownPreviewCommand($markdown);
        $response = $this->service->renderPreview($command);
        $html = $response->html;

        // Verify Table
        self::assertStringContainsString('<table', $html);
        self::assertStringContainsString('Cell 1', $html);

        // Verify Image
        self::assertStringContainsString('<img src="https://example.com/img.png"', $html);

        // Verify Heading Permalinks
        self::assertStringContainsString('id="title-1"', $html);

        // Verify Code Block preserved newlines (Entities handled)
        self::assertStringContainsString('<pre><code>', $html);
        self::assertStringContainsString('function hello() {', $html);
        self::assertStringContainsString('console.log', $html);
        self::assertStringContainsString("\n", $html); // Key for multi-line
    }
}
