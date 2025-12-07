# REST API Plan

## 1. Resources

User — `users` table
Note — `notes` table
NoteCollaborator — `note_collaborators` table
Auth / Session — not a DB table but uses `users` and Symfony security + JWT

---

## 2. Endpoint REST API

All JSON responses use `{ "data": ..., "meta": {...} }` for list endpoints and `{ "data": ... }` for single resources unless otherwise stated. All request/response bodies shown as JSON objects.

Common query parameters (where applicable):
`page` (integer, default `1`) — offset pagination page
`per_page` (integer, default `10`, max `100`)
`q` (string) — free-text search over `title` + `description`
`label` (string|comma-separated) — label filter; behaves as OR across provided labels
`sort` (string) — allowed values: `created_at.desc` (default)

Authentication:
Bearer JWT header: `Authorization: Bearer <token>` for authenticated endpoints. Short-lived access tokens paired with longer-lived refresh tokens; refresh rotates tokens and may be revoked on logout.

### Auth (registration / login / logout / refresh)

#### Register

Method:`POST`
Path:`/api/auth/register`
Description:Create user account and return JWT. Auto-login.
Request JSON:

```json
{
  "email": "user@example.com",
  "password": "P@ssw0rd!"
}
```

Response 201:

```json
{
  "data": {
    "id": 123,
    "uuid": "uuid",
    "email": "user@example.com",
    "created_at": "2025-12-05T..."
  },
  "meta": {
    "token": "<jwt>",
    "expires_in": 3600,
    "refresh_token": "<refresh-jwt>",
    "refresh_expires_in": 1209600
  }
}
```

Errors:

  `400` — validation error (invalid email, password too short).
  `409` — email already registered.

#### Login

Method:`POST`
Path:`/api/auth/login`
Description:Exchange email+password for JWT.
Request JSON:

```json
{ "email": "user@example.com", "password": "P@ssw0rd!" }
```

Response 200:

```json
{
  "data": { "id": 123, "uuid": "...", "email": "user@example.com" },
  "meta": {
    "token": "<jwt>",
    "expires_in": 3600,
    "refresh_token": "<refresh-jwt>",
    "refresh_expires_in": 1209600
  }
}
```

Errors:

  `401` — invalid credentials.
  `429` — rate limit.

#### Refresh access token

Method:`POST`
Path:`/api/auth/refresh`
Description:Exchange a valid refresh token for a new access token (rotate refresh token). Does not require existing access token.
Request JSON:

```json
{ "refresh_token": "<refresh-jwt>" }
```

Response 200:

```json
{
  "data": { "id": 123, "uuid": "...", "email": "user@example.com" },
  "meta": {
    "token": "<new-access-jwt>",
    "expires_in": 3600,
    "refresh_token": "<new-refresh-jwt>",
    "refresh_expires_in": 1209600
  }
}
```

Errors:

  `400` — malformed/blacklisted refresh token.
  `401` — expired or invalid refresh token.
  `429` — rate limit.

#### Logout

Method:`POST`
Path:`/api/auth/logout`
Auth required:Yes
Description:Revoke current token / server-side session (if stored). For JWT stateless, instruct client to discard token; optionally add token to blacklist.
Response 204 No Content
Errors:`401` unauthorized.

---

### Notes

Base path: `/api/notes`

#### Create note

Method:`POST`
Path:`/api/notes`
Auth required:Yes
Description:Create a new note; server generates `url_token` (UUIDv4) on first save. Default `visibility=private`.
Request JSON:

```json
{
  "title": "My recipe",
  "description": "Markdown content...",
  "labels": ["dessert żółć","baking kick"],
  "visibility": "private"  // optional, allowed: "private","public","draft"
}
```

Response 201:

```json
{
  "data": {
    "id": 10,
    "owner_id": 123,
    "url_token": "uuid-v4",
    "title": "...",
    "description": "...",
    "labels": ["dessert żółć","baking kick"],
    "visibility": "private",
    "created_at": "...",
    "updated_at": "..."
  }
}
```

Errors:`400` validation (title/description length), `401` unauthorized.

Notes:`url_token` generation must be attempted in transaction; on UUID collision return `409` and retry at application level.

#### Read note (by ID) — owner/collaborator view

Method:`GET`
Path:`/api/notes/{id}`
Auth required:Yes (only owners/collaborators)
Response 200:note object (same as above)
Errors:`403` forbidden, `404` not found.

#### Public read by token (public notes)

Method:`GET`
Path:`/api/public/notes/{url_token}`
Auth required:No
Description:Returns rendered metadata and full `description` (markdown rendered on client). Only returns content if note `visibility='public'`.
Response 200:

```json
{
  "data": {
    "title": "...",
    "description": "...",
    "labels": [...],
    "created_at": "..."
  }
}
```

Errors:`404` not found or `403` if note exists but not public.

#### Update note

Method:`PATCH`
Path:`/api/notes/{id}`
Auth required:Yes (owner or collaborator)
Request JSON:any subset of fields:

```json
{
  "title": "New title",
  "description": "updated markdown",
  "labels": ["a","b"],
  "visibility": "public"
}
```

Behavior:Changes saved only on explicit save. If `url_token` was regenerated client must persist change.
Response 200:updated note object
Errors:`400` validation, `403` unauthorized, `404` not found.

#### Delete note

Method:`DELETE`
Path:`/api/notes/{id}`
Auth required:Yes (owner only)
Response:`204 No Content`
Behavior:DB `ON DELETE CASCADE` ensures `note_collaborators` removed. Application must ensure logging and redirect.
Errors:`403`, `404`.

---

### Regenerate URL token
Method:`POST`
Path:`/api/notes/{id}/url/regenerate`
Auth required:Yes (owner or collaborator)
Description:
  Generates a new random UUIDv4 URL token for the note and persists it immediately in the database.
  The previous URL becomes invalid instantly — any attempt to access the old link must return an access-denied or invalid-link message.
  After successful regeneration the endpoint returns the new token, and the client is expected to reload the page to display the updated URL (as required by the PRD).
  If a technical error occurs, a clear error message is returned and the previous URL remains valid.

Request JSON: none
Response 200:
```json
{
  "data": {
    "url_token": "new-uuid"
  }
}
```

Errors:
  `403` — insufficient permissions (not owner or collaborator)
  `409` — unique-constraint violation on token (very rare)
  `500` — regeneration failed; previous URL remains valid
Behavior summary:
  New URL token is generated and saved immediately.
  Old URL becomes invalid right away.
  Client should reload the page to show the updated URL.

---

#### List notes (dashboard — owner)

Method:`GET`
Path:`/api/notes`
Auth required:Yes
Query params:`page`, `per_page`, `q`, `label`
Behavior:Returns notes owned by current user; default sorted by `created_at DESC`. Search `q` runs full-text search against `search_vector_simple` or simple ILIKE fallback. `label` applies `labels && ARRAY[...]` (OR logic). Pagination via offset.
Response 200:

```json
{
  "data": [ { note }, ... ],
  "meta": { "page": 1, "per_page": 10, "total": 42 }
}
```

Errors:`401`.

---

### Note Collaborators

Base path: `/api/notes/{note_id}/collaborators`

#### Add collaborator (by email)

Method:`POST`
Path:`/api/notes/{note_id}/collaborators`
Auth required:Yes (owner or collaborator)
Request JSON:

```json
{ "email": "collab@example.com" }
```

Behavior:Adds collaborator row with `email` stored as provided and `user_id` set if an account with that email exists. Enforce unique `(note_id, lower(email))`.
Response 201:

```json
{ "data": { "id": 45, "note_id": 10, "email": "collab@example.com", "user_id": 999, "created_at": "..." } }
```

Errors:`400` invalid email, `409` duplicate, `403` unauthorized.

#### Remove collaborator (by collaborator id or email)

Method:`DELETE`
Path options:

  `/api/notes/{note_id}/collaborators/{collab_id}` OR
  `/api/notes/{note_id}/collaborators?email=collab@example.com`
Auth required:Yes
Behavior:Owner or collaborator who is removing themselves can remove. If collaborator removes self, immediately loses access. Application must prevent collaborator removing owner.
Response 204
Errors:`403`, `404`.

#### List collaborators

Method:`GET`
Path:`/api/notes/{note_id}/collaborators`
Auth required:Yes (owner or collaborator)
Response 200:list of collaborator objects.

---

### Public user catalog

#### Get public catalog by user UUID

Method:`GET`
Path:`/api/public/users/{user_uuid}/notes`
Auth required:No
Query params:`page`, `per_page`, `q`, `label`
Description:Returns public notes (`visibility = 'public'`) for the user. Paginated. If user does not exist or has no public notes, return `404` or empty list with friendly message. (PRD: show "No such user" if user not found.)
Response 200:

```json
{ "data": [ { title, description_excerpt, labels, created_at, url_token }, ... ], "meta": {...} }
```

Errors:`404` if user UUID not found (preferred to match PRD).

---

### Search (global in dashboard / catalog)

Method:`GET`
Path:`/api/search/notes`
Auth required:Dashboard searches require auth (search limited to user's accessible notes). Catalog search is public limited to user uuid.
Query:`q`, `label`, `page`, `per_page`, `uuid`
Implementation:Use `search_vector_simple` GIN index and `to_tsquery` for `q` when available; support `label:` prefix parsing in `q` or accept `label` param. For labels, use `labels && ARRAY[...]` to implement OR behavior. UUID is the user uuid pointing user catalog notes list.
Response:paginated list.

---

### Markdown preview

Method:`POST`
Path:`/api/notes/preview`
Auth required:Yes
Request JSON:

```json
{ "description": "markdown text" }
```

Response 200:

```json
{ "data": { "html": "<p>...</p>" } }
```

Notes:Server-side markdown rendering for preview only. No persistence.

---

### Error response format

Errors return `{"error": {"code": <int>, "message": "...", "details": {...}}}`.

Common HTTP codes:

`200 OK`, `201 Created`, `204 No Content`
`400 Bad Request` — validation
`401 Unauthorized` — missing/invalid auth
`403 Forbidden` — insufficient permissions
`404 Not Found` — resource not found
`409 Conflict` — unique constraint (email, url_token, collaborator duplicate)
`429 Too Many Requests` — rate limits

---

## 3. Authentication and Authorization

Chosen mechanism:Symfony Security + JWT (e.g. `lexik/jwt-authentication-bundle`) for stateless API tokens. Passwords hashed with Symfony native password hasher (argon2id or bcrypt) stored in `users.password_hash`. Login issues return `401`.

Why:matches technical stack (PHP 8.2, Symfony 7). JWT provides simple bearer token flow for SPA/HTMX clients and mobile.

Session & CSRF:

For HTML forms rendered server-side (Twig + HTMX), use session-based auth and standard CSRF tokens for state-changing operations. For pure JSON API clients use JWT Bearer header.
Provide both flows: cookie-based session for web, JWT for API clients if needed (configure guards).

Role & Voter-based authorization:

Use Symfony Voters to enforce domain rules (owner vs collaborator vs other). Example voters:

  `NoteAccessVoter` controls view/edit/delete/regenerate actions.
  `CollaboratorVoter` controls adding/removing collaborators.
Voters consult DB relations (`notes.owner_id`, `note_collaborators`).

RLS and DB-level policies:

MVP decision:Do not use Row-Level Security (RLS). Enforce access control in application layer (Symfony Voters). (This follows PRD: "No RLS in MVP")
Document recommendation for future: implement RLS after MVP for defense-in-depth.

Token revocation:

For immediate logout/compromise handle possibility to blacklist tokens (store jti in DB) or use short-lived access tokens + refresh tokens.

Rate limiting / abuse prevention:

Apply API-level rate limits (per IP, per user):

  Example: `100 requests/min` per token, stricter for auth endpoints (login/register) to mitigate brute-force.
Return `429` on limit reached.

Transport security:TLS required for all endpoints.

---

## 4. Validation and Business Logic

### Validation rules (mapped from schema & PRD)

**Users:**
  `email` required, valid format, case-insensitive uniqueness (DB index `UNIQUE lower(email)` enforced). API must reject duplicates with `409`.
  `password` required on register; min length `8` (configurable). Hash server-side.

**Notes:**
  `title` required, max `255` characters (validated in API). DB does not enforce length — API must enforce.
  `description` required, max `10000` characters (API validation).
  `labels` array of strings; each label allows full unicode with alphanumerical characters; normalize duplicates (dedupe) at API level before persisting.
  `visibility` allowed enum: `public`, `private`, `draft`. API must validate values.
  `url_token` server-generated UUIDv4 unique. On creation generate with `gen_random_uuid()` or application UUID generator. Handle uniqueness conflicts (`409`).

**Note collaborators:**
  `email` required, basic email format validation.
  Unique constraint `(note_id, lower(email))` — API should catch DB conflict and return `409`.
  `user_id` is nullable; if exists, set to user id. On user registration, implement background/transactional linking or a `POST /api/users/{id}/link-collaborations` flow to link previous collaborator rows by email if necessary.

### Business rules mapped to API (explicit)

**Create / Edit / Delete:**
  Only owner/collaborator can edit; only owner can delete. Enforced by Voters returning `403` otherwise.
No autosave:All edits persist only on explicit `Save` calls — UI must call `PATCH /api/notes/{id}` only when user saves.
Regenerate URL:Regeneration invalidates previous token immediately. Implementation chosen: server persists new token atomically on `POST /api/notes/{id}/url/regenerate`.

**Visibility semantics:**
  `public`: can be read via `/api/public/notes/{url_token}` or via public user catalog.
  `private`: inaccessible to anonymous users; owner/collaborators only.
  `draft`: treated same as `private` for public access; may be used in UI to indicate unpublished.

**Collaborator rights:**
  Collaborators have same rights as owner except cannot delete the note. They can add/remove other collaborators (including themselves). API enforces collaborators cannot delete the note.
Self-removal:Collaborator removing self (`DELETE /api/notes/{note_id}/collaborators?email=me`) must immediately revoke access; ensure changes are transactional and Voter checks use current data.

**Search and labels:**
  Support `label:` prefix in search; server should parse `q` and route to label filtering logic.
  Fulltext search uses `search_vector_simple` (GIN). For languages other than basic, fallback to ILIKE if tsvector absent.

**Pagination & ordering:**
  Default `per_page=10`, `sort=created_at.desc` per PRD. Use DB index `(owner_id, created_at DESC)` for dashboard queries.

**Deletion cascade:**
  `DELETE /api/notes/{id}` must remove `note_collaborators` via DB cascade; application may log deletion for audit (Monolog).

### DB constraints vs API responsibilities

DB-enforced:
  FK integrity: `notes.owner_id` references `users.id` with `ON DELETE CASCADE`.
  `note_collaborators.note_id` FK `ON DELETE CASCADE`.
  Unique indexes: `ux_users_email_lower`, `ux_note_collaborators_noteid_email_lower`, `url_token` uniqueness.
  Types: `note_visibility` enum.

API/enforcement:
  Field lengths (title, description) — API must validate.
  Email format — API must validate.
  Label normalization and deduplication — API must handle.
  Business authorizations (owner vs collaborator) — handled in application layer (Voters).
  Regeneration semantics (atomic invalidation) — API orchestrates and persists.
  Error mapping from DB errors (unique constraint violations) to `409` responses.

### Performance considerations and index usage (impacts on queries / endpoints)

Use GIN index on `labels` to support label OR queries (`labels && ARRAY[...]`) — used by `/api/notes` and `/api/public/users/{uuid}/notes`.
Use GIN index on `search_vector_simple` for `q` full-text search — used by `/api/search/notes`.
Use composite BTREE indices `(owner_id, created_at DESC)` and `(owner_id, visibility, created_at DESC)` for efficient dashboard and public-catalog queries.
For pagination on large datasets consider switching to keyset pagination in future; MVP uses offset pagination per PRD.

---

## Appendix — Assumptions and notes
**Markdown rendering:** Server provides HTML preview via `/api/notes/preview`; final rendering occurs client-side on public view pages using sanitized HTML to avoid XSS. Use a safe markdown renderer and HTML sanitizer.
**Error translation:** Map DB constraint errors (unique violations) to HTTP `409` with clear messages (e.g. `email already exists`, `url_token collision`, `collaborator already exists`).
