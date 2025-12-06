<?php

declare(strict_types=1);

namespace App\Dto;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Mapuje enum DB: note_visibility ('public','private','draft')
 * oraz wymusza poprawne wartości w DTO/Commandach.
 */
enum NoteVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Draft = 'draft';
}

/**
 * Podstawowe dane użytkownika zwracane przez API (login/register).
 *
 * Powiązanie z DB:
 *  - users.id
 *  - users.uuid
 *  - users.email
 *  - users.created_at
 */
final readonly class UserDto
{
    public function __construct(
        public int $id,
        public Uuid $uuid,
        public string $email,
        public DateTimeImmutable $createdAt,
    ) {}
}

/**
 * Meta do odpowiedzi auth (token + TTL).
 *
 * Nie jest encją DB, ale logicznie powiązany z sesją / JWT użytkownika.
 */
final readonly class AuthTokenMetaDto
{
    public function __construct(
        public string $token,
        public int $expiresIn,
    ) {}
}

/**
 * DTO notatki do widoków właściciela / współedytora.
 *
 * Powiązanie z DB: tabela notes.
 */
final readonly class NoteDto
{
    public function __construct(
        public int $id,                     // notes.id
        public int $ownerId,                // notes.owner_id
        public Uuid $urlToken,              // notes.url_token
        public string $title,               // notes.title
        public string $description,         // notes.description
        /** @var list<string> */
        public array $labels,               // notes.labels (TEXT[])
        public NoteVisibility $visibility,  // notes.visibility (ENUM)
        public DateTimeImmutable $createdAt, // notes.created_at
        public DateTimeImmutable $updatedAt, // notes.updated_at
    ) {}
}

/**
 * DTO publicznego widoku notatki po url_token.
 *
 * Powiązanie z DB: tabela notes, ale celowo eksponuje tylko subset pól.
 */
final readonly class PublicNoteDetailsDto
{
    public function __construct(
        public string $title,               // notes.title
        public string $description,         // notes.description (pełna treść)
        /** @var list<string> */
        public array $labels,               // notes.labels
        public DateTimeImmutable $createdAt, // notes.created_at
    ) {}
}

/**
 * DTO pojedynczej notatki w publicznym katalogu użytkownika.
 *
 * Powiązanie z DB: notes.* + pochodna description_excerpt z notes.description.
 */
final readonly class PublicCatalogNoteDto
{
    public function __construct(
        public string $title,               // notes.title
        public string $descriptionExcerpt,  // fragment notes.description (obliczany w aplikacji)
        /** @var list<string> */
        public array $labels,               // notes.labels
        public DateTimeImmutable $createdAt, // notes.created_at
        public Uuid $urlToken,              // notes.url_token
    ) {}
}

/**
 * DTO współpracownika notatki.
 *
 * Powiązanie z DB: tabela note_collaborators.
 */
final readonly class NoteCollaboratorDto
{
    public function __construct(
        public int $id,                     // note_collaborators.id
        public int $noteId,                 // note_collaborators.note_id
        public string $email,               // note_collaborators.email
        public ?int $userId,                // note_collaborators.user_id (nullable)
        public DateTimeImmutable $createdAt, // note_collaborators.created_at
    ) {}
}

/**
 * Uniwersalne meta paginacji dla endpointów listujących.
 */
final readonly class PaginationMetaDto
{
    public function __construct(
        public int $page,
        public int $perPage,
        public int $total,
    ) {}
}

/**
 * Minimalny wrapper na odpowiedź listującą: { data: [...], meta: {...} }.
 *
 * @template T
 */
final readonly class ListResponseDto
{
    /**
     * @param list<mixed> $data lista DTO danego typu (NoteDto, PublicCatalogNoteDto, NoteCollaboratorDto, ...)
     */
    public function __construct(
        public array $data,
        public PaginationMetaDto $meta,
    ) {}
}

/**
 * Wrapper na odpowiedź pojedynczego zasobu: { data: ... }.
 *
 * @template T
 */
final readonly class SingleResponseDto
{
    public function __construct(
        public mixed $data,
    ) {}
}

/**
 * DTO wynikowy regeneracji tokenu URL.
 *
 * Powiązanie z DB: notes.url_token (nowa wartość).
 */
final readonly class NoteUrlTokenDto
{
    public function __construct(
        public Uuid $urlToken,
    ) {}
}

/**
 * DTO wynikowy preview markdown: { html: "<p>...</p>" }.
 *
 * Powiązanie pośrednie: pochodzi z notes.description / wejścia użytkownika.
 */
final readonly class MarkdownPreviewDto
{
    public function __construct(
        public string $html,
    ) {}
}

/**
 * Command: rejestracja użytkownika.
 *
 * Wejście z /api/auth/register.
 */
final readonly class RegisterUserCommand
{
    public function __construct(
        public string $email,
        public string $plainPassword,
    ) {}
}

/**
 * Command: logowanie użytkownika.
 *
 * Wejście z /api/auth/login.
 */
final readonly class LoginUserCommand
{
    public function __construct(
        public string $email,
        public string $plainPassword,
    ) {}
}

/**
 * Command: stworzenie nowej notatki.
 *
 * Wejście z POST /api/notes.
 * owner_id pochodzi z kontekstu security (zalogowany użytkownik), nie z body.
 */
final readonly class CreateNoteCommand
{
    /**
     * @param list<string>|null $labels
     */
    public function __construct(
        public string $title,
        public string $description,
        public ?array $labels = null,
        public ?NoteVisibility $visibility = null, // default 'private' gdy null
    ) {}
}

/**
 * Command: częściowa aktualizacja notatki.
 *
 * Wejście z PATCH /api/notes/{id}.
 * noteId pochodzi z parametru ścieżki.
 */
final readonly class UpdateNoteCommand
{
    /**
     * @param list<string>|null $labels
     */
    public function __construct(
        public int $noteId,
        public ?string $title = null,
        public ?string $description = null,
        public ?array $labels = null,
        public ?NoteVisibility $visibility = null,
    ) {}

    /**
     * Czy komenda niesie jakiekolwiek zmiany.
     */
    public function hasChanges(): bool
    {
        return $this->title !== null
            || $this->description !== null
            || $this->labels !== null
            || $this->visibility !== null;
    }
}

/**
 * Command: regeneracja url_token notatki.
 *
 * Wejście z POST /api/notes/{id}/url/regenerate.
 */
final readonly class RegenerateNoteUrlTokenCommand
{
    public function __construct(
        public int $noteId,
    ) {}
}

/**
 * Command: dodanie współpracownika do notatki po e-mailu.
 *
 * Wejście z POST /api/notes/{note_id}/collaborators.
 */
final readonly class AddNoteCollaboratorCommand
{
    public function __construct(
        public int $noteId,
        public string $email,
    ) {}
}

/**
 * Command: usunięcie współpracownika po ID.
 *
 * Wejście z DELETE /api/notes/{note_id}/collaborators/{collab_id}.
 */
final readonly class RemoveNoteCollaboratorByIdCommand
{
    public function __construct(
        public int $noteId,
        public int $collaboratorId,
    ) {}
}

/**
 * Command: usunięcie współpracownika po e-mailu.
 *
 * Wejście z DELETE /api/notes/{note_id}/collaborators?email=...
 */
final readonly class RemoveNoteCollaboratorByEmailCommand
{
    public function __construct(
        public int $noteId,
        public string $email,
    ) {}
}

/**
 * Command/query: parametry paginacji.
 *
 * Wspólne dla wielu endpointów listujących (dashboard, katalog publiczny, search).
 */
final readonly class PaginationQuery
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 10,
    ) {}
}

/**
 * Command/query: filtrowanie listy notatek właściciela (/api/notes).
 *
 * Powiązanie z DB:
 *  - q -> search_vector_simple / tsvector (lub ILIKE fallback)
 *  - labels -> notes.labels (GIN, OR)
 */
final readonly class NoteDashboardListQuery
{
    /**
     * @param list<string>|null $labels
     */
    public function __construct(
        public PaginationQuery $pagination,
        public ?string $q = null,
        public ?array $labels = null,
        public string $sort = 'created_at.desc',
    ) {}
}

/**
 * Command/query: publiczny katalog użytkownika (/api/public/users/{user_uuid}/notes).
 *
 * Powiązanie z DB:
 *  - userUuid -> users.uuid
 *  - visibility = 'public'
 *  - q/labels jak w dashboardzie, ale ograniczone do publicznych notatek.
 */
final readonly class PublicUserCatalogQuery
{
    /**
     * @param list<string>|null $labels
     */
    public function __construct(
        public Uuid $userUuid,
        public PaginationQuery $pagination,
        public ?string $q = null,
        public ?array $labels = null,
        public string $sort = 'created_at.desc',
    ) {}
}

/**
 * Command/query: globalne wyszukiwanie notatek (/api/search/notes).
 *
 * Powiązanie z DB:
 *  - q -> notes.search_vector_simple (GIN + to_tsquery)
 *  - labels -> notes.labels
 */
final readonly class SearchNotesQuery
{
    /**
     * @param list<string>|null $labels
     */
    public function __construct(
        public PaginationQuery $pagination,
        public ?string $q = null,
        public ?array $labels = null,
    ) {}
}

/**
 * Command: preview markdown (/api/notes/preview).
 *
 * Powiązanie pośrednie: treść jak w notes.description, ale bez zapisu do DB.
 */
final readonly class MarkdownPreviewCommand
{
    public function __construct(
        public string $description,
    ) {}
}
