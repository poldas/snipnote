<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Service: NoteViewSelector
 * -------------------------
 * Odpowiada za wybór "motywu" (theme) wyświetlania notatki publicznej na podstawie jej etykiet (labels).
 *
 * Działanie:
 * 1. Analizuje etykiety przypisane do notatki.
 * 2. Sprawdza, czy którakolwiek z nich odpowiada zdefiniowanym słowom kluczowym (np. 'recipe', 'todo').
 * 3. Zwraca identyfikator motywu (string), który jest następnie przekazywany do widoku (Twig).
 *
 * Użycie:
 * W kontrolerze PublicNotePageController do ustawienia zmiennej `theme`.
 */
final class NoteViewSelector
{
    /**
     * Mapowanie: Słowo kluczowe w labelu => Identyfikator motywu
     */
    private const THEME_MAPPING = [
        'recipe' => 'recipe', // Aktywuje assets/styles/recipe_view.css
        'todo' => 'todo',     // Aktywuje assets/styles/todo_view.css
    ];

    private const DEFAULT_THEME = 'default';

    /**
     * Zwraca identyfikator motywu na podstawie listy etykiet.
     * Priorytet ma pierwsze dopasowanie znalezione w tablicy THEME_MAPPING.
     *
     * @param string[] $labels Tablica etykiet notatki (np. ['przepis', 'obiad'])
     * @return string Identyfikator motywu ('default', 'recipe', 'todo')
     */
    public function getTheme(array $labels): string
    {
        // Normalize labels to lower case for comparison
        $normalizedLabels = array_map('strtolower', $labels);

        foreach (self::THEME_MAPPING as $keyword => $theme) {
            if (in_array($keyword, $normalizedLabels, true)) {
                return $theme;
            }
        }

        return self::DEFAULT_THEME;
    }
}
