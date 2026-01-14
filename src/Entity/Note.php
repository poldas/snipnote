<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NoteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NoteRepository::class)]
#[ORM\Table(name: 'notes')]
class Note
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'notes')]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(name: 'url_token', type: Types::STRING, unique: true, columnDefinition: 'uuid')]
    private string $urlToken;

    #[ORM\Column(type: Types::TEXT)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    /** @var list<string> */
    #[ORM\Column(type: 'text_array')]
    private array $labels = [];

    #[ORM\Column(enumType: NoteVisibility::class, type: Types::STRING, columnDefinition: 'note_visibility')]
    private NoteVisibility $visibility = NoteVisibility::Private;

    #[ORM\Column(name: 'search_vector_simple', type: Types::STRING, nullable: true, columnDefinition: 'tsvector')]
    private ?string $searchVectorSimple = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, NoteCollaborator> */
    #[ORM\OneToMany(mappedBy: 'note', targetEntity: NoteCollaborator::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $collaborators;

    /**
     * @param list<string> $labels
     */
    public function __construct(User $owner, string $title, string $description, array $labels = [], ?NoteVisibility $visibility = null)
    {
        $this->owner = $owner;
        $this->title = $title;
        $this->description = $description;
        $this->labels = $labels;
        $this->visibility = $visibility ?? NoteVisibility::Private;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->collaborators = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): void
    {
        $this->owner = $owner;
    }

    public function getUrlToken(): string
    {
        return $this->urlToken;
    }

    public function setUrlToken(string $urlToken): void
    {
        $this->urlToken = $urlToken;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return list<string>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * @param list<string> $labels
     */
    public function setLabels(array $labels): void
    {
        $this->labels = $labels;
    }

    public function getVisibility(): NoteVisibility
    {
        return $this->visibility;
    }

    public function setVisibility(NoteVisibility $visibility): void
    {
        $this->visibility = $visibility;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touchUpdatedAt(\DateTimeImmutable $moment = new \DateTimeImmutable()): void
    {
        $this->updatedAt = $moment;
    }

    /**
     * @return Collection<int, NoteCollaborator>
     */
    public function getCollaborators(): Collection
    {
        return $this->collaborators;
    }
}
