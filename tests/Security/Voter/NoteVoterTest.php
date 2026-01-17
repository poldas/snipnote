<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Note;
use App\Entity\User;
use App\Repository\NoteCollaboratorRepository;
use App\Security\Voter\NoteVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class NoteVoterTest extends TestCase
{
    public function testOwnerHasFullAccess(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $note = new Note($owner, 'title', 'body');

        $token = self::createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($owner);

        $collaboratorRepository = self::createStub(NoteCollaboratorRepository::class);

        $voter = new NoteVoter($collaboratorRepository);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $note, [NoteVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $note, [NoteVoter::EDIT]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $note, [NoteVoter::DELETE]));
    }

    public function testCollaboratorCanViewAndEditButNotDelete(): void
    {
        $owner = new User('owner@example.com', 'hash');
        $collaborator = new User('collab@example.com', 'hash');
        $note = new Note($owner, 'title', 'body');

        $token = self::createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($collaborator);

        $collaboratorRepository = self::createStub(NoteCollaboratorRepository::class);
        $collaboratorRepository->method('isCollaborator')->with($note, $collaborator)->willReturn(true);

        $voter = new NoteVoter($collaboratorRepository);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $note, [NoteVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $note, [NoteVoter::EDIT]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $note, [NoteVoter::DELETE]));
    }
}
