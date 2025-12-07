<?php

declare(strict_types=1);

namespace App\Service;

use App\Command\Note\GenerateMarkdownPreviewCommand;
use App\DTO\Note\NotesMarkdownPreviewResponseDto;
use League\CommonMark\ConverterInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

final class MarkdownPreviewService
{
    public function __construct(
        private readonly ConverterInterface $markdownConverter,
        private readonly HtmlSanitizerInterface $sanitizer,
    ) {}

    public function renderPreview(GenerateMarkdownPreviewCommand $command): NotesMarkdownPreviewResponseDto
    {
        $rawHtml = $this->markdownConverter->convert($command->description)->getContent();
        $safeHtml = $this->sanitizer->sanitize($rawHtml);

        return new NotesMarkdownPreviewResponseDto($safeHtml);
    }
}
