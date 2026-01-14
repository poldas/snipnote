<?php

declare(strict_types=1);

namespace App\Service\Markdown;

use League\CommonMark\ConverterInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MarkdownConverterFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        #[Autowire('%app.markdown.config%')]
        private readonly array $config,
    ) {
    }

    public function __invoke(): ConverterInterface
    {
        $environment = new Environment($this->config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new ExternalLinkExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new HeadingPermalinkExtension());

        return new MarkdownConverter($environment);
    }
}
