
# AI MVP FLOW

## Planowanie
### Opis projektu MVP
**ARTEFAKT:**
plan, opis projektu [project_desctiption](./docs/artefacts/01-artefact-project-description-from-stakeholder.md)

Na samym początku należy mieć **przygotowany opis projektu** zgodnie z planem <opis_projektu>

<opis_projektu>

Aplikacja - {NAZWA} (MVP)

1. Główny problem
Jaki problem rozwiązuje twoja aplikacja?

2. Najmniejszy zestaw funkcjonalności
Co wchodzi w skład MVP?

3. Co NIE wchodzi w zakres MVP
Co nie wchodzi w skład MVP?

4. Kryteria sukcesu
Jaki cel chciałbym osiągnąć?

5. Założenia projektu
preferencje odnośnie sposobu wytwarzania, technologii, np użycie DDD, preferowanie composable api, używanie gotowych bibliotek zewnętrznych, sugestie technologiczne, odnośnie UX/UI, gitflow, docker itd.

</opis_projektu>

### Stack technologiczny
**ARTEFAKT:**
stack technologiczny, np. [technical_stack](./docs/artefacts/04-artefact-technical-stack.md)

### Sesja planistyczna i podsumowanie
**!!! Wymagana jest interakcja użytkownika**

**ARTEFAKT:**
podsumowanie sesji planistycznej, np. [project_details](./docs/artefacts/02-artefact-prd-planning-summary.md)

Opis projektu dodajemy do prompta w pole <project_description> [assistant prd planning](.ai/delivery/assistant-prd-planning-session.mdc).

Po zakończeniu generowania sesji planistycznej (na żądanie, albo po wyczerpaniu limitu sesji), dodajemy prompt podsumowujący na samym dole konwersacji [prompt podsumowujący](.ai/delivery/assistant-prd-planing-summary.mdc)

W wyniku otrzymujemy podsumowanie sesji planistycznej, które zostanie użyte do wygenerowania PRD.

### Generowanie PRD
**ARTEFAKT:** 
dokument prd [prd](./docs/artefacts/03-artefact-prd.md)

Do asystenta prd [assistant prd](.ai/delivery/assistant-prd.mdc) dodajemy kontekts:
 - [project_desctiption](./docs/artefacts/01-artefact-project-description-from-stakeholder.md)
 - [project_details](./docs/artefacts/02-artefact-prd-planning-summary.md)

 W wyniku otrzymujemy dokument PRD.

### Planowanie Bazy Danych DB i podsumowanie planowania
**!!! Wymagana jest interakcja użytkownika**

**ARTEFAKT:**
[db_session_notes](./docs/claude-artefacts/claude-db-planning-summary.md)
podsumowanie planowania DB

**ASSISTANT**
[assistant db planning](.ai/delivery/assistant-db-planning.mdc)
kontekst:
- [prd](./docs/artefacts/03-artefact-prd.md)
- [technical_stack](./docs/artefacts/04-artefact-technical-stack.md)

Po zakończeniu generowania sesji planistycznej DB (na żądanie, albo po wyczerpaniu limitu sesji), dodajemy prompt podsumowujący na samym dole konwersacji [prompt podsumowujący](.ai/delivery/assistant-db-planning-summary.mdc)

W wyniku otrzymujemy podsumowanie sesji planistycznej, które zostanie użyte do wygenerowania schematu bazy danych.

### Generowanie schematu bazy danych (gotowej migracji)
**ARTEFAKT:**
[db_schema](./docs/claude-artefacts/claude-db-create-migration.md)
schemat, migracja DB do wykonania przez supabase cli

**ASSISTANT**
[assistant db create](.ai/delivery/assistant-db-create-plan.mdc)
kontekst:
- [prd](./docs/artefacts/03-artefact-prd.md)
- [db_session_notes](./docs/claude-artefacts/claude-db-planning-summary.md)
- [technical_stack](./docs/artefacts/04-artefact-technical-stack.md)

## Implementacja

### Szkielet projektu (manualnie)
- npm create vite@latest snipnote -- --template vue
- cd snipnote
- npm install @supabase/supabase-js
- git init
- touch .env
- touch supabase.js
- mkdir -p src/db
- supabase init
- supabase start
- supabase login
- supabase link --project-ref <project id> z https://supabase.com/dashboard/project/<ID>/settings/general
- supabase status -o env
- supabase migration up --debug // lokalnie
- supabase db push // na serwerze zdalnym
- **google auth setup** [google oauth](./docs/GOOGLE_AUTH_SETUP.md)

**supabase.js**
```
import { createClient } from '@supabase/supabase-js'

const supabaseUrl = import.meta.env.VITE_SUPABASE_URL
const supabasePublishableKey = import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY

export const supabase = createClient(supabaseUrl, supabasePublishableKey)
```

### Migracja bazy danych
Migracje umieszczamy w ./supabase/migrations/
np. ./supabase/migrations/20251020210525_initialize_db.sql

```
supabase migration up // serwer lokalny
supabase db reset // usuwa dane resetuje baze i wykonuje migracje
supabase db push // serwer zdalny
```

### Generowanie typów z postgres schema
**ARTEFAKT**:
[database_models](./src/db/database.types.ts)
database model types

supabase gen types typescript --local > src/db/database.types.ts

### Tworzenie planu API (specyfikacja api)
**ARTEFAKT:** [api_specification_plan](./docs/claude-artefacts/claude-api-plan-specification.md)
specyfikacja API

**ASSISTANT**
[assistant api specification](.ai/delivery/assistant-api-plan-specification.mdc)
kontekst:
- [db_schema](./docs/claude-artefacts/claude-db-create-migration.md)
- [prd](./docs/artefacts/03-artefact-prd.md)
- [technical_stack](./docs/artefacts/04-artefact-technical-stack.md)

W wyniku otrzymujemy gotową specyfikację implementacji API.

### Generowanie typów typescript DTO
**ARTEFAKT:** [dto_type_definitions](./src/types.ts)
typy typescript DTO

**ASSISTANT**
[assistant api dto generate](.ai/delivery/assistant-api-dto-generate-types.mdc)
kontekst:
- [database_models](../../src/db/database.types.ts)
- [api_specification_plan](./docs/artefacts/06-artefact-api-plan-specification-gpt.md)

### Plan implementacji endpointa(ów)
**ARTEFAKT:** 
[endpoint_implementation_plan](./docs/claude-artefacts/claude-api-implementation-plan-notes.md)
plan implementacji endpointa 

**ASSISTANT**
[assistant api implementation plan](.ai/delivery/assistant-api-implementation-plan.mdc)
kontekst:
- [api_specification_plan](./docs/claude-artefacts/claude-api-plan-specification.md)
- [db_schema](./docs/claude-artefacts/claude-db-create-migration.md)
- [dto_type_definitions](./src/types.ts)
- [technical_stack](./docs/artefacts/04-artefact-technical-stack.md)
- [implementation_rules](.ai/rules-programming.mdc)[UI Rules](../rules-ui-implementation.mdc)

### IMPLEMENTACJA endpointu workflow 3x3
**!!! Wymagana jest interakcja użytkownika**

**ARTEFAKT**: kod, testy

**ASSISTANT**
[assistant api implementation endpoint](.ai/delivery/assistant-api-implement-endpoint.mdc)
kontekst:
- [endpoint_implementation_plan](./docs/claude-artefacts/claude-api-implementation-plan-notes.md)
- [dto_type_definitions](./src/types.ts)
- [implementation_rules](.ai/rules-programming.mdc)
- [technical_stack](./docs/artefacts/04-artefact-technical-stack.md)

per iteracja agenta ai
```
Feedback do dotychczasowych działań:
[lista punktowana z odniesieniem do poszczególnych zaraportowanych kroków] <- jeżeli krok został wykonany w 100% dobrze, pomiń punkt lub napisz "OK"

Feedback do planowanych kroków:
[lista punktowana z odniesieniem do poszczególnych pranowanych kroków] <- jeżeli nie masz zastrzeżeń, napisz "OK"

[pozostałe uwagi] <- jeżeli masz dodatkowe uwagi, napisz je tutaj
```

### Planowanie architektury UI i podsumowanie
**!!! Wymagana jest interakcja użytkownika**

**ARTEFAKT:**
[ui_planning_summary](./docs/claude-artefacts/claude-ui-planning-summary.md)
podsumowanie planowania UI, UI plan 

**ASSISTANT**
[assistant ui planning](.ai/delivery/assistant-ui-planning-session.mdc)
kontekst:
- [prd](./docs/artefacts/03-artefact-prd.md)
- [technical_stack](./docs/artefacts/04-artefact-technical-stack.md)
- [api_specification_plan](./docs/claude-artefacts/claude-api-plan-specification.md)
- [additional context](../rules-ui-implementation.mdc)

Na końcu podsumowujemy promptem podumowaującym [assistant ui planning summary](.ai/delivery/assistant-ui-planning-summary.mdc)

### Generowanie wysokopoziomowego planu UI
**ARTEFAKT:**
[ui_plan](./docs/claude-artefacts/claude-ui-high-level-plan.md)
 high level ui plan

**ASSISTANT**
[assistant ui planning](.ai/delivery/assistant-ui-high-level-plan.mdc)
kontekst:
- [prd](./docs/artefacts/03-artefact-prd.md)
- [api_specification_plan](./docs/claude-artefacts/claude-api-plan-specification.md)
- [ui_planning_summary](./docs/claude-artefacts/claude-ui-planning-summary.md)

### Generowanie szczegółowego planu architektury ui
**ARTEFAKT:**
[ui_implementation_plan](./docs/claude-artefacts/[view name]-view-implementation-plan.md)
 ui implementation plan 

**ASSISTANT**
[assistant ui detail planning](.ai/delivery/assistant-ui-detail-implementation-plan.mdc)
kontekst:
- [prd](./docs/artefacts/03-artefact-prd.md)
- [ui_plan](./docs/artefacts/10-artefact-ui-high-level-plan.md)
- [user_stories] z dokumentu [prd]
- [api_specification_plan](./docs/artefacts/06-artefact-api-plan-specification-gpt.md)
- [endpoint_implementation_plan](./docs/artefacts/08a-artefact-api-implementation-plan.md)
- [dto_type_definitions](./src/types.ts)
- [technical_stack](./docs/artefacts/04-artefact-technical-stack.md)

### Implementacja widoków 3x3
**!!! Wymagana jest interakcja użytkownika**

**ARTEFAKT**: kod, widoki

**ASSISTANT**
[assistant ui implementation view](.ai/delivery/assistant-ui-implement-view.mdc)
kontekst:
- [ui_implementation_plan](./docs/claude-artefacts/[view name]-view-implementation-plan.md)
- [implementation_rules](../rules-programming.mdc)
- [implementation_ui_rules](../rules-ui-implementation.mdc)
- [dto_type_definitions](./src/types.ts)
