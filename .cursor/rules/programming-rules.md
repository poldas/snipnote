### Ogólne zasady AI / Cursor
- Scope patcha: generuj mały, spójny patch (diff) zawierający tylko powiązane pliki (entity, repo, service, controller, twig, migration, test). Max ~500 LOC.
- Commit format: <scope>: <krótkie-opis> (np. notes: add label index migration).
- Jeśli brakuje danych niezbędnych do implementacji → odpowiedz nie wiem i zaproponuj 2 warianty implementacji.
- Zawsze dołącz krótkie 1–2 zdania trade-offs (szybkość vs utrzymanie).
- Generuj unified diff (nie pełne pliki, jeśli możliwe).
- Jeżeli zmienia się DB: dołącz Doctrine migration, surowe SQL, oraz krótki rollback plan.
- Załącz kontekst plików: entity, repo, service, controller, twig (jeśli dotyczy).
- DB change → dołącz migration + surowe SQL + rollback note.
- Testy: dołącz minimalny test dla zmian domenowych/auth.
- Lint & static checks: generuj kod zgodny z PSR-12; uruchom PHP-CS-Fixer i PHPStan (lvl 5).
- Jeśli brakuje danych wymaganych do poprawnego kodu — odpowiedz „nie wiem” i podaj 2 alternatywy realizacji.
- Docker-readiness: wygenerowany kod powinien działać w standardowym obrazie PHP-FPM 8.2 + nginx + postgres; dołącz modyfikację docker-compose tylko gdy konieczne.
- Max LOC per patch: 500 LOC; jeśli potrzeba więcej, rozbij na spójne patchy.
- Implement → Test → Feedback never 50 features then test.

### Symfony & Doctrine
- aplikacja działa w dockerz, ZAWSZE uruchamiaj poprzez 'docker compose <command>'
- testy uruchamiaj poprzez `./localbin/test.sh`
- testy e2e uruchamiaj poprzez `./localbin/test_e2e.sh`
- na koniec pracy zawsze zamykaj aplikację `docker compose down`
- Architektura: prosty podział — Entity → Repository → Service (logika domenowa) → Controller (thin). Nie pełne DDD, tylko jawne granice.
- Target: Symfony 8. Preferuj attributes (routing, DI, Doctrine mapping).
- Chudy `services.yaml`: nie trzymaj tam logiki konfiguracji bibliotek ani parametrów. Używaj Fabryk i dedykowanych plików w `config/packages/`.
- DI: wstrzykiwanie zależności przez konstruktor; używaj atrybutu `#[Autowire('%param%')]` zamiast jawnej konfiguracji argumentów w YAML.
- Kontrolery: thin — logika w serwisach.
- DI: wstrzykiwanie zależności, unikaj statycznych helperów.
- CLI & SQL: Używaj `doctrine:dbal:run-sql` zamiast przestarzałego `doctrine:query:sql` w skryptach i CI.
- Limity danych: Zmieniając limity (np. długość tekstu), aktualizuj je we wszystkich warstwach: Baza Danych -> Walidacja (Entity/DTO) -> Frontend (HTML/JS) -> Konfiguracja Serwisów (np. HtmlSanitizer).
- Formularze: Symfony Forms + Validator (server authoritative).
- Używaj QueryBuilder/DTO, unikaj nadmiernej hydracji, profiluj zapytania i dodaj indeksy tam, gdzie wyszukiwanie (title/description/labels) jest krytyczne
- Standard: Kod: PSR-12; typowanie parametrów/metod; PHPStan poziom 5 (MVP), phpVersion: 8.2.
- Zero-warning policy: testy muszą przechodzić bez komunikatów 'Notice' i 'Warning'. Używaj `createStub()` dla danych wejściowych i `createMock()` tylko dla weryfikacji interakcji.
- Typowanie: wymagane dla parametrów i zwracanych wartości; używaj union types, promoted properties, readonly tam gdzie sensowne.
- Używaj maker:bundle do scaffoldingu, ale ręcznie dopracowuj wygenerowany kod.
- Mapowania: PHP attributes preferowane dla Symfony i Doctrine
- Stosuj gotowe i sprawdzone już rozwiązania i pakiety np. stosujesz gotowe pakiety (Symfony + API Platform/lexik/jwt),

### Frontend (UI) — reguły (Twig + HTMX 2+ + Tailwind + Fluent 2 UI)
- Komponenty: małe, pojedyncze partiale Twig (max ~200 LOC).
- Każdy partial ma jasno zdefiniowane wejście (parametry) i nie trzyma logiki domenowej.
- Interakcje: HTMX tylko dla prostych fragmentów (formularze, listy, podgląd).
- Dla złożonych interakcji wyodrębnij osobny endpoint lub rozważ mały komponent JS.
- Styling: Tailwind utility-first; nie generuj nadmiarowych klas — preferuj zwięzłe klasy i tokeny w tailwind.config.
- CSS: unikaj `!important`. Stosuj jednostki `rem` dla lepszej skalowalności i hierarchii.
- Markdown: renderowanie po stronie serwera; zawsze sanitizuj HTML przed wysłaniem do klienta.
- Formularze: używaj Symfony Forms → prosty rendering Twig; walidacja zarówno klient + server (server authoritative).
- Accessibility: pola formularzy powinny mieć label, błędy przy polach, keyboard focus dla modali.

- Używaj komponentów Fluent 2 UI

### Autoryzacja
- używaj istniejących Symfony bundles.