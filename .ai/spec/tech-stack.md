### Tech stack / środowisko
#### Backend
PHP 8.4+
Symfony 8.0 (attributes dla routingu, DI, Doctrine)
Doctrine ORM 3.5 (+ doctrine/migrations)
Doctrine DBAL 4.x
maker-bundle do scaffoldingu
PostgreSQL (dev/test/prod)

#### Frontend
Twig jako główny templating
HTMX 2+ do partial refresh / formularzy / list
Tailwind CSS jako jedyny system styli (opcjonalny prosty build) + Fluent2
Minimalny vanilla JS tylko tam, gdzie HTMX nie wystarcza

#### Autoryzacja (PostgreSQL + Symfony)
Wbudowany Symfony Security (authenticator-based)
Użytkownicy i sesje trzymane w PostgreSQL (encje User, tabele auth)
Password hashing: native password hasher (argon2id / bcrypt)
JWT: lexik/jwt-authentication-bundle do wystawiania i weryfikacji tokenów
Refresh tokens: gesdinet/jwt-refresh-token-bundle
Role i uprawnienia przez role hierarchy + Voter dla reguł domenowych

#### API / komunikacja
Standard: klasyczne kontrolery Symfony (HTML + JSON)

#### Docker / infra
Docker Compose: php-fpm 8.2, nginx, postgres (+ ewentualnie mailhog)
Konfiguracja kompatybilna z uruchomieniem CLI: `php bin/console`, `phpunit`, `phpstan`

#### Testy / jakość
PHPUnit + Symfony Test Pack
DoctrineFixturesBundle / własne seedy do danych testowych
PHP-CS-Fixer (PSR-12)
PHPStan na poziomie 5
E2E Playwright
Symfony Profiler + Monolog dla debugowania/obserwowalności
Testy integracyjne na osobnej bazie postgres
Uruchamiane przez github actionw / workflow