## 1. Lista tabel z kolumnami, typami danych i ograniczeniami

### `users`

```sql
CREATE TABLE users (
  id                BIGSERIAL PRIMARY KEY,
  uuid              UUID NOT NULL DEFAULT gen_random_uuid(), -- publiczny identyfikator katalogu użytkownika
  email             TEXT NOT NULL,
  password_hash     TEXT NOT NULL,
  created_at        TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at        TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);
```

Ograniczenia / uwagi:

* Unikalność email case-insensitive realizujemy indeksem `UNIQUE (lower(email))`.
* `uuid` używany publicznie (katalog użytkownika).

---

### `notes`

```sql
CREATE TYPE note_visibility AS ENUM ('public','private','draft');

CREATE TABLE notes (
  id                  BIGSERIAL PRIMARY KEY,
  owner_id            BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  url_token           UUID NOT NULL UNIQUE, -- publiczny losowy token (UUIDv4)
  title               TEXT NOT NULL,        -- walidacje długości w aplikacji (<=255)
  description         TEXT NOT NULL,        -- markdown, walidacja długości w aplikacji
  labels              TEXT[] NOT NULL DEFAULT '{}', -- lokalne labeli dla notatki
  visibility          note_visibility NOT NULL DEFAULT 'private',
  search_vector_simple tsvector,            -- tsvector (konfiguracja 'simple')
  created_at          TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at          TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);
```

Ograniczenia / uwagi:

* `url_token` — UUIDv4, unikalny. Przy kolizji aplikacja powinna zwrócić błąd (409) i ponowić generację.
* Brak DB CHECK dla długości pól (walidacja w aplikacji — zgodnie z decyzją).

---

### `note_collaborators`

```sql
CREATE TABLE note_collaborators (
  id          BIGSERIAL PRIMARY KEY,
  note_id     BIGINT NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
  email       TEXT NOT NULL,          -- adres e-mail współedytora (zapisany jak wpisany)
  user_id     BIGINT NULL REFERENCES users(id) ON DELETE SET NULL, -- automatyczne powiązanie po rejestracji
  created_at  TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);
```

Ograniczenia / uwagi:

* Unikalność: `(note_id, lower(email))` — zapobiega duplikatom adresów indywidualnie dla notatki.
* `user_id` nullable — pozwala na dodanie współedytora zanim zarejestruje się konto.

---

## 2. Relacje między tabelami (kardynalność)

* `users (1) — (N) notes`
  Jeden użytkownik (owner) może mieć wiele notatek. (`notes.owner_id` → `users.id`)
* `notes (1) — (N) note_collaborators`
  Jedna notatka może mieć wielu współedytorów. (`note_collaborators.note_id` → `notes.id`)
* `users (1) — (N) note_collaborators` (opcjonalne powiązanie)
  `note_collaborators.user_id` wskazuje na `users.id` po rejestracji; powiązanie wiele-do-wielu realizowane przez `note_collaborators` + `notes.owner_id` (bez dodatkowej tabeli).

Kardynalności:

* users ↔ notes: 1:N
* notes ↔ note_collaborators: 1:N
* users ↔ note_collaborators (przez user_id): 1:N (opcjonalnie)

---

## 3. Indeksy (wydajność zapytań)

### Indeksy na `users`

```sql
-- email unikalny case-insensitive
CREATE UNIQUE INDEX ux_users_email_lower ON users (lower(email));
-- szybsze wyszukiwanie katalogu po uuid
CREATE UNIQUE INDEX ux_users_uuid ON users (uuid);
```

### Indeksy na `notes`

```sql
-- GIN dla etykiet (text[]) — wyszukiwanie OR po labelach
CREATE INDEX ix_notes_labels_gin ON notes USING GIN (labels);

-- GIN dla fulltext (tsvector) z konfiguracją 'simple'
CREATE INDEX ix_notes_search_vector_gin ON notes USING GIN (search_vector_simple);

-- BTREE dla (visibility, url_token) — szybkie wyszukiwanie publicznych/prywatnych po tokenie
CREATE INDEX ix_notes_visibility_urltoken ON notes (visibility, url_token);

-- BTREE dla listowania dashboardu właściciela w sortowaniu created_at DESC
CREATE INDEX ix_notes_owner_createdat_desc ON notes (owner_id, created_at DESC);

-- Opcjonalny indeks przydatny dla katalogu publicznego (owner -> publiczne notatki)
CREATE INDEX ix_notes_owner_visibility_createdat ON notes (owner_id, visibility, created_at DESC);
```

### Indeksy na `note_collaborators`

```sql
-- Unikalność współedytora per notatka (case-insensitive email)
CREATE UNIQUE INDEX ux_note_collaborators_noteid_email_lower ON note_collaborators (note_id, lower(email));

-- Szybkie wyszukiwanie współedytowanych notatek danego użytkownika/emaila
CREATE INDEX ix_note_collaborators_userid ON note_collaborators (user_id);
CREATE INDEX ix_note_collaborators_email_lower ON note_collaborators (lower(email));
```

---

## 4. DDL pomocnicze: funkcja trigger i trigger do aktualizacji `search_vector_simple`

```sql
-- Funkcja aktualizująca search_vector_simple używając konfiguracji 'simple'
CREATE FUNCTION notes_search_vector_update() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  NEW.search_vector_simple := to_tsvector('simple', coalesce(NEW.title,'') || ' ' || coalesce(NEW.description,''));
  RETURN NEW;
END
$$;

-- Trigger
CREATE TRIGGER trg_notes_search_vector BEFORE INSERT OR UPDATE
  ON notes FOR EACH ROW EXECUTE FUNCTION notes_search_vector_update();
```

(Uwaga: alternatywnie można aktualizować `search_vector_simple` w warstwie aplikacji podczas zapisu.)

---

## 5. Zasady PostgreSQL / bezpieczeństwo (DB-level)

1. **Relacje i spójność**

   * FK `notes.owner_id -> users.id` z `ON DELETE CASCADE` (przy usunięciu użytkownika usuwamy jego notatki).
   * FK `note_collaborators.note_id -> notes.id` z `ON DELETE CASCADE` (usunięcie notatki usuwa powiązania).

2. **Autoryzacja**

   * Autoryzacja/zasady dostępu **po stronie aplikacji** (Symfony Voter). Nie stosujemy Row-Level Security (RLS) w MVP — prostsze utrzymanie i zgodne z decyzją.

3. **Indexy dla wyszukiwania**

   * GIN dla `labels` (text[]) i `search_vector_simple` (tsvector) — dobre dla OR-wyszukiwania po labelach i fulltext.

4. **Unikalność i porównania email**

   * Case-insensitive unikalność dla `users.email` oraz unikalność `(note_id, lower(email))` dla współedytorów.

5. **Brak CHECK dla długości**

   * Długości tytułu/description kontrolowane w aplikacji (zgodnie z decyzją).

6. **Transakcyjność regeneracji URL**

   * Regeneracja `url_token` to prosty `UPDATE notes SET url_token = <new_uuid> WHERE id = <id>` w transakcji; stary token przestaje działać natychmiast dzięki unikalności i atomowości transakcji.

---

## 6. Dodatkowe uwagi projektowe / najlepsze praktyki

* `url_token` jako UUIDv4: prosty mechanizm; aplikacja powinna obsługiwać konflikt (unikalność) i ponowną próbę generacji (lub zgłosić 409).
* `labels TEXT[]` + GIN: uproszcza MVP i zapewnia OR-logikę wyszukiwania (np. `labels && ARRAY['tag1','tag2']`). Duplikaty labeli per notatka można normalizować w aplikacji (usuwać powtórzenia).
* `search_vector_simple` z konfiguracją `simple` pozwala prostą, język-agnostyczną pełnotekstową obsługę. Jeśli w przyszłości wymagana będzie lepsza obsługa językowa, można dodać kolumny tsvector per-language lub zmienić konfigurację.
* Paginação: offset-pagination (limit/offset) jest zgodna z wymaganiami MVP; indeks `(owner_id, created_at DESC)` zapewnia wydajność listowania dashboardu. Dla bardzo dużych zbiorów rozważyć keyset pagination w przyszłości.
* Usuwanie notatki przez właściciela: usunięcie w DB (DELETE FROM notes WHERE id=...) wraz z `ON DELETE CASCADE` usunie powiązane labeli/kolaboratorów/indeksy.
* Logi zmian (URL, usunięcia) — w decyzjach PRD: wystarczą logi aplikacji (Monolog), więc brak tabel audit w MVP.
* Migracje: zapisać powyższe CREATE TYPE / CREATE TABLE / CREATE INDEX / CREATE FUNCTION / CREATE TRIGGER jako migrację Doctrine (SQL).

---

## 7. Podsumowanie — plik DDL (skrócony, kompletna kolejność wykonywania)

1. `CREATE EXTENSION IF NOT EXISTS pgcrypto;` (jeśli używamy `gen_random_uuid()`).
2. `CREATE TYPE note_visibility ...`
3. `CREATE TABLE users (...)`
4. `CREATE UNIQUE INDEX ux_users_email_lower ON users (lower(email));`
5. `CREATE TABLE notes (...)`
6. `CREATE TABLE note_collaborators (...)`
7. funkcja `notes_search_vector_update()` + trigger `trg_notes_search_vector`
8. wszystkie indeksy GIN i BTREE wymienione wyżej