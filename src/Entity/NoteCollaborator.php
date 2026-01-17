<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NoteCollaboratorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NoteCollaboratorRepository::class)]
#[ORM\Table(name: 'note_collaborators')]
class NoteCollaborator
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Note::class, inversedBy: 'collaborators')]
    #[ORM\JoinColumn(name: 'note_id', nullable: false, onDelete: 'CASCADE')]
    private Note $note;

    #[ORM\Column(type: Types::TEXT)]
    private string $email;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Note $note, string $email, ?User $user = null)
    {
        $this->note = $note;
        $this->email = $this->sanitizeEmail($email);
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNote(): Note
    {
        return $this->note;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $this->sanitizeEmail($email);
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function sanitizeEmail(string $email): string
    {
        return mb_trim($email);
    }
}
