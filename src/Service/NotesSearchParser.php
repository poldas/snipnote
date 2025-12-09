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

        $original = trim((string) $q);
        if ($original === '') {
            return ['labels' => [], 'text' => null];
        }

        // Normalize commas and spacing to simplify parsing of multi-value labels.
        $normalized = preg_replace('/\s*,\s*/u', ',', $original);
        $normalized = preg_replace('/label:\s+/u', 'label:', $normalized);

        $pattern = '/label:(?:"([^"]+)"|\'([^\']+)\'|([^\s]+))/u';
        preg_match_all($pattern, $normalized, $matches);

        $labelCandidates = array_merge($matches[1], $matches[2], $matches[3]);
        foreach ($labelCandidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $maybeLabels = preg_split('/[,]+/', $candidate) ?: [];
            foreach ($maybeLabels as $label) {
                $label = trim($label);
                if (str_starts_with($label, 'label:')) {
                    $label = substr($label, 6);
                }
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
        }

        $text = trim(preg_replace($pattern, '', $normalized));
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return [
            'labels' => array_values(array_unique($labels)),
            'text' => $text === '' ? null : $text,
        ];
    }
}
