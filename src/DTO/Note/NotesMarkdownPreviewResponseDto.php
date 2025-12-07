<?php

declare(strict_types=1);

namespace App\DTO\Note;

final readonly class NotesMarkdownPreviewResponseDto
{
    public function __construct(public string $html) {}
}
