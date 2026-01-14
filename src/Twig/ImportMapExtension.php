<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\AssetMapper\ImportMap\ImportMapRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ImportMapExtension extends AbstractExtension
{
    public function __construct(
        private readonly ImportMapRenderer $importMapRenderer,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('importmap_safe', [$this, 'renderSafe'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Renders importmap but removes keys that should not be visible on public pages.
     *
     * @param list<string> $excludePatterns
     */
    public function renderSafe(string $entryPoint, array $excludePatterns = []): string
    {
        $html = $this->importMapRenderer->render([$entryPoint]);

        $result = preg_replace_callback('/<script type="importmap".*?>(.*?)<\/script>/s', function ($matches) use ($excludePatterns) {
            $json = $matches[1];
            $data = json_decode($json, true);

            if (isset($data['imports']) && \is_array($data['imports'])) {
                foreach ($data['imports'] as $key => $value) {
                    foreach ($excludePatterns as $pattern) {
                        // Match if pattern is in the key (e.g. 'app' or 'note_form')
                        if (str_contains($key, $pattern)) {
                            unset($data['imports'][$key]);
                            break;
                        }
                    }
                }
            }

            return '<script type="importmap" data-turbo-track="reload">'.json_encode($data, \JSON_UNESCAPED_SLASHES).'</script>';
        }, $html);

        return $result ?? $html;
    }
}
