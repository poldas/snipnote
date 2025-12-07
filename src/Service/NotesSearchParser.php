<?php

declare(strict_types=1);

namespace App\Service;

final class NotesSearchParser
{
    /**
     * @return array{labels: list<string>, text: ?string}
     */
    public function parse(?string $q): array
    {
        $labels = [];
        $textParts = [];

        $normalized = trim((string) $q);
        if ($normalized === '') {
            return ['labels' => [], 'text' => null];
        }

        $tokens = preg_split('/\s+/', $normalized) ?: [];
        foreach ($tokens as $token) {
            if (str_starts_with($token, 'label:')) {
                $labelString = substr($token, 6);
                $maybeLabels = preg_split('/[,]+/', $labelString) ?: [];
                foreach ($maybeLabels as $label) {
                    $label = trim($label);
                    if ($label !== '') {
                        $labels[] = $label;
                    }
                }
                continue;
            }

            $textParts[] = $token;
        }

        $text = trim(implode(' ', $textParts));

        return [
            'labels' => array_values(array_unique($labels)),
            'text' => $text === '' ? null : $text,
        ];
    }
}

