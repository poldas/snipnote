<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Note;
use App\Entity\User;
use App\Repository\NoteCollaboratorRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Note>
 */
final class NoteVoter extends Voter
{
    public const VIEW = 'NOTE_VIEW';
    public const EDIT = 'NOTE_EDIT';
    public const DELETE = 'NOTE_DELETE';

    public function __construct(
        private readonly NoteCollaboratorRepository $collaboratorRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Note
            && \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Note $note */
        $note = $subject;

        return match ($attribute) {
            self::VIEW, self::EDIT => $this->canViewOrEdit($note, $user),
            self::DELETE => $note->getOwner() === $user,
            default => false,
        };
    }

    private function canViewOrEdit(Note $note, User $user): bool
    {
        if ($note->getOwner() === $user) {
            return true;
        }

        return $this->collaboratorRepository->isCollaborator($note, $user);
    }
}
