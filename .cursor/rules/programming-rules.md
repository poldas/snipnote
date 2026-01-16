### Ogólne zasady AI / Cursor
- Scope patcha: generuj mały, spójny patch (diff) zawierający tylko powiązane pliki (entity, repo, service, controller, twig, migration, test). Max ~500 LOC.
- Commit format: <scope>: <krótkie-opis> (np. notes: add label index migration).
- Jeśli brakuje danych niezbędnych do implementacji → odpowiedz nie wiem i zaproponuj 2 warianty implementacji.
- Zawsze dołącz krótkie 1–2 zdania trade-offs (szybkość vs utrzymanie).
- Generuj unified diff (nie pełne pliki, jeśli możliwe).
- Jeżeli zmienia się DB: dołącz Doctrine migration, surowe SQL, oraz krótki rollback plan.
- Załącz kontekst plików: entity, repo, service, controller, twig (jeśli dotyczy).
- Testy: Obowiązkowo dołącz testy do każdej zmiany logiki lub refaktoryzacji (Unit, Integration lub E2E). Każda funkcjonalność musi mieć pokrycie testowe przed uznaniem zadania za zakończone.
- Czerwona Ścieżka (Red Path): Testy muszą obejmować scenariusze negatywne (brak uprawnień, błędne dane, brzegowe przypadki bezpieczeństwa).
- Lint & static checks: generuj kod zgodny z PSR-12; uruchom PHP-CS-Fixer i PHPStan (lvl 6).
- PHPStan Cleanliness: Nie używaj zbędnego rzutowania typów (np. `(string)` na zmiennej, która już jest stringiem). Ufaj analizie statycznej. Rozwiązuj błędy `cast.useless` poprzez usunięcie rzutowania.
- Docker-readiness: wygenerowany kod powinien działać w standardowym obrazie PHP-FPM 8.4 + apache + postgres.
- Implement → Test → Feedback: nigdy nie implementuj 50 funkcjonalności bez testów po drodze.
- reużywaj komponenty jeżeli to możliwe, nie duplikuj kodu, zawsze najpierw sprawdzaj czy dana funkcjonalność już istnieje

### Code Quality & Readability
- **Nie pisz zawiłego kodu**: Unikaj skomplikowanych warunków inline (np. wielokrotne `if` zagnieżdżone w kontrolerach).
- **Metody pomocnicze**: Zamiast rozbudowanych instrukcji warunkowych, wydzielaj logikę do małych, nazwanych metod (np. `shouldShowPrivateNotes($user)`), które jasno komunikują intencję.
- **Odpowiedzialność Repozytorium**: Logika filtrowania danych (szczególnie `public` vs `private`) musi znajdować się wyłącznie w Repozytorium. Kontroler tylko przekazuje kryteria (DTO).

### Symfony & Doctrine
- Aplikacja działa w Dockerze, ZAWSZE uruchamiaj poprzez `docker compose exec app <command>`.
- Testy uruchamiaj poprzez `./localbin/test.sh`.
- Testy E2E uruchamiaj poprzez `./localbin/test_e2e.sh`.
- Na koniec pracy zawsze zamykaj aplikację `docker compose down`.
- Architektura: Entity → Repository → Service (logika domenowa) → Controller (thin). Nie pełne DDD, tylko jawne granice.
- Target: Symfony 8.0. Używaj atrybutów (routing, DI, Doctrine mapping).
- Chudy `services.yaml`: używaj Fabryk i konfiguracji w `config/packages/`. Unikaj trzymania tam parametrów.
- DI: wstrzykiwanie przez konstruktor; używaj atrybutu `#[Autowire('%param%')]`. Unikaj statycznych helperów.
- CLI & SQL: Używaj `doctrine:dbal:run-sql` zamiast przestarzałego `doctrine:query:sql`.
- Limity danych: aktualizuj limity we wszystkich warstwach: DB -> Walidacja (Entity/DTO) -> Frontend -> Serwisy (np. HtmlSanitizer).
- Typowanie: wymagane dla parametrów i zwracanych wartości (union types, promoted properties, readonly).
- Zero-warning policy: testy muszą przechodzić bez 'Notice' i 'Warning'.
- Używaj QueryBuilder/DTO, unikaj nadmiernej hydracji, profiluj zapytania i dodaj indeksy tam, gdzie wyszukiwanie jest krytyczne.
- Używaj `maker-bundle` do scaffoldingu, ale ręcznie dopracowuj wygenerowany kod.
- Stosuj sprawdzone pakiety (np. LexikJWT, Gesdinet RefreshToken, Symfony Security).

### API & Walidacja (Rygor)
- **Brak ręcznego rzutowania**: W kontrolerach nie rzutuj typów z payloadu (np. UNIKAJ `(string)$payload['title']`).
- **Strongly Typed DTOs**: Pozwól konstruktorom DTO/Command na wyrzucenie `\TypeError` przy błędnych danych z JSON.
- **Exception Mapping**: `ExceptionListener` musi mapować:
    - `\TypeError` oraz `ValidationException` -> **400 Bad Request**.
    - `AccessDeniedException` oraz `AccessDeniedHttpException` -> **403 Forbidden**.
    - `NotFoundHttpException` -> **404 Not Found**.
- **JSON Decoding**: Zawsze używaj `decodeJson` z obsługą błędów składni (Invalid JSON payload).

### Testowanie (PHPUnit 12 & Security)
- **PHPUnit 12**: Używaj atrybutu `#[DataProvider]`, metody providerów muszą być `static`.
- **Asercje**: Używaj `self::assert...` zamiast `$this->assert...`.
- **Czerwona Ścieżka**: Każdy endpoint API musi mieć testy dla kodów 400 (bad data), 401 (no auth), 403 (wrong user), 404 (not found).
- **Test Infrastructure**: Przy mockowaniu repozytoriów w `WebTestCase`, używaj refleksji, aby ustawić ID dla encji stworzonych w pamięci (zapobieganie `ORMInvalidArgumentException`).
- **PHPStan (Level 6)**: Zawsze specyfikuj typy zawartości tablic (np. `array<int, Note>`).

### Frontend (UI) — Twig + HTMX 2+ + Tailwind + Fluent 2 UI
- Komponenty: małe partiale Twig (max ~200 LOC).
- Interakcje: HTMX dla prostych fragmentów. Złożone interakcje -> Stimulus controller.
- Styling: Tailwind utility-first; jednostki `rem`. Unikaj `!important`.
- Nie generuj nadmiarowych klas Tailwind — preferuj zwięzłe klasy i tokeny w `tailwind.config`.
- Markdown: renderowanie po stronie serwera + rygorystyczna sanitizacja (HtmlSanitizer).
- Formularze: Symfony Forms + Validator (server authoritative). Walidacja klient + serwer.
- Accessibility: label dla każdego pola, błędy widoczne, keyboard focus.
- UI: Stosuj komponenty i zasady Fluent 2 UI.

### Autoryzacja i Bezpieczeństwo
- Używaj Symfony Security (Voters, Passport, Authenticator).
- JWT: Rygorystyczna weryfikacja (algorytmy, exp, sub).
- XSS: Zawsze sanitizuj HTML generowany z Markdown przed wysłaniem do klienta.
