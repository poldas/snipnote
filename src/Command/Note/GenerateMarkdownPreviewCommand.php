<?php

declare(strict_types=1);

namespace App\Command\Note;

final readonly class GenerateMarkdownPreviewCommand
{
    public function __construct(public string $description) {}
}
