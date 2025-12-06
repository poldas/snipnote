<conversation_summary>
<decisions>
1. Labele będą przechowywane w kolumnie `labels text[]` z GIN-indexem.
2. Współedytorzy będą w tabeli `note_collaborators` z kolumnami: `note_id`, `email`, `user_id (nullable)` i unikalnością `(note_id, lower(email))`.
3. Token URL będzie UUIDv4, przechowywany jako `url_token UUID UNIQUE`; przy kolizji zwracany będzie błąd z bazy (HTTP 409).
4. Widoczność będzie typem ENUM `public | private | draft` z indeksem BTREE `(visibility, url_token)`.
5. Wyszukiwanie pełnotekstowe będzie oparte wyłącznie o `search_vector_simple` (`tsvector` z konfiguracją `simple`) z GIN-indexem.
6. Paginacja w dashboardzie i katalogu użytkownika będzie klasyczną offset-pagination; indeks `(owner_id, created_at DESC)` będzie używany do sortowania.
7. Regeneracja URL to transakcyjny UPDATE, bez dodatkowych tabel; stary URL natychmiast nieważny.
8. Autoryzacja tylko w aplikacji (Symfony Voter), bez Row-Level Security w bazie.
9. Ograniczenia długości tytułu/opisu są walidowane wyłącznie w aplikacji — bez DB CHECK.
10. Skalowalność: brak partycjonowania; przy wolumenie ~1000 notatek i 2–5 labeli utrzymywane będą tylko podstawowe indeksy GIN/BTREE.
11. Nie stosujemy refresh tokens; login oparty o standardowy mechanizm Symfony + natywny password hasher (najprostsze gotowe rozwiązanie).
12. Logi zmian (URL, usunięcia) nie wymagają osobnej tabeli — wystarczą zwykłe logi aplikacji.
13. Testy mają obejmować wyłącznie podstawowy CRUD dla najważniejszych operacji.

</decisions>

<matched_recommendations>
1. Uproszczone przechowywanie labeli jako `text[]` + GIN-index zapewnia szybkie wyszukiwanie OR i minimalną złożoność w MVP.
2. Rozdzielenie współedytorów do osobnej tabeli z kluczem email + opcjonalnym user_id umożliwia współdzielenie także przed rejestracją.
3. UUIDv4 jako URL token eliminuje konflikt nazewnictwa i upraszcza obsługę — kolizje rozwiązane prostą obsługą błędu DB.
4. ENUM dla widoczności minimalizuje błędy i umożliwia szybkie filtrowanie dzięki indeksowi `(visibility, url_token)`.
5. Pojedynczy `search_vector_simple` upraszcza logikę i pozwala wyszukiwać wielojęzyczne treści bez rozbudowanych konfiguracji.
6. Indeks `(owner_id, created_at DESC)` gwarantuje szybki dashboard i prostą paginację.
7. Transakcyjny model regeneracji URL zapewnia atomiczność i natychmiastową nieważność poprzedniego tokenu.
8. Autoryzacja wyłącznie po stronie Symfony pozwala uprościć DB i szybciej wykonać MVP.
9. Rezygnacja z CHECK-ów upraszcza layer DB i pozostawia walidację w domenie aplikacji.
10. Podstawowe CRUD-owe testy pokryją kluczowe scenariusze MVP bez rozbudowanych przypadków.

</matched_recommendations>

<database_planning_summary>
Projektowana baza danych dla MVP składa się z trzech głównych encji: `users`, `notes` oraz `note_collaborators`.

**Users** zawiera minimalne pola: `id`, `uuid`, `email` (unikalne, case-insensitive), `password_hash`, znaczniki czasu. Służy jako właściciel notatek i opcjonalne powiązanie dla współedytorów. Logika autoryzacji realizowana jest wyłącznie w Symfony — baza utrzymuje jedynie integralność kluczy.

**Notes** przechowuje: `id`, `owner_id`, `title`, `description`, `labels text[]`, `url_token UUID`, `visibility ENUM('public','private','draft')`, `search_vector_simple` oraz metadane czasu. Wyszukiwanie oparte jest o tsvector (konfiguracja `simple`), co pozwala na obsługę wielu języków jednocześnie przy minimalnej złożoności. URL token jest jedynym publicznym kluczem dostępowym. Widoczność determinuje dostęp dla anonimów, a dostęp edycyjny dla zalogowanych kontrolowany jest w aplikacji.

**Note_collaborators** pozwala na przyznawanie uprawnień edycji po adresie email, z automatycznym powiązaniem z kontem po rejestracji. Usuwanie notatki usuwa wszystkie powiązania dzięki `ON DELETE CASCADE`.

**Indeksowanie** obejmuje GIN dla `labels` i `search_vector_simple`, oraz BTREE dla `(owner_id, created_at DESC)` i `(visibility, url_token)`. To zapewnia szybkie operacje wyszukiwania i listowania w dashboardzie oraz katalogu publicznym.

**Bezpieczeństwo** na poziomie bazy jest ograniczone do poprawnych kluczy i spójności relacji. Cała logika dostępu — kto może czytać/edytować/usunąć — realizowana jest w Symfony Voter. Hasła przechowywane są wyłącznie w postaci hashy, z natywnym hasherem Symfony.

**Skalowalność** jest wystarczająca dzięki GIN-indeksom i prostemu modelowi danych. 
Przy przewidywanych ~1000 notatkach i kilku labelach per notatka baza będzie działać bardzo szybko bez partycjonowania.

**Testy** obejmą tylko podstawowe CRUD dla najważniejszych operacji: tworzenie/edycja/usunięcie notatek, dodawanie/odejmowanie współedytorów oraz odczyt publiczny/private z zachowaniem zasad widoczności.

Wszystkie krytyczne decyzje są ustalone i kompletne dla wygenerowania pełnego DDL, migracji Doctrine i szkieletu testów — planowanie bazy danych dla MVP jest zamknięte.
</database_planning_summary>
</conversation_summary>