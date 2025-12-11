<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Note;
use App\Entity\NoteCollaborator;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NoteCollaborator>
 *
 * @method NoteCollaborator|null find($id, $lockMode = null, $lockVersion = null)
 * @method NoteCollaborator|null findOneBy(array $criteria, array $orderBy = null)
 * @method array<int, NoteCollaborator> findAll()
 * @method array<int, NoteCollaborator> findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NoteCollaboratorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NoteCollaborator::class);
    }

    public function findByNoteAndId(int $noteId, int $collaboratorId): ?NoteCollaborator
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.id = :collaboratorId')
            ->andWhere('IDENTITY(c.note) = :noteId')
            ->setParameter('collaboratorId', $collaboratorId)
            ->setParameter('noteId', $noteId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByNoteAndEmail(int $noteId, string $email): ?NoteCollaborator
    {
        return $this->createQueryBuilder('c')
            ->andWhere('IDENTITY(c.note) = :noteId')
            ->andWhere('LOWER(c.email) = LOWER(:email)')
            ->setParameter('noteId', $noteId)
            ->setParameter('email', trim($email))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<NoteCollaborator>
     */
    public function findAllByNote(int $noteId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('IDENTITY(c.note) = :noteId')
            ->setParameter('noteId', $noteId)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function existsForNoteAndEmail(int $noteId, string $email): bool
    {
        return (bool) $this->createQueryBuilder('c')
            ->select('1')
            ->andWhere('IDENTITY(c.note) = :noteId')
            ->andWhere('LOWER(c.email) = LOWER(:email)')
            ->setParameter('noteId', $noteId)
            ->setParameter('email', trim($email))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isCollaborator(Note $note, User $user): bool
    {
        return (bool) $this->createQueryBuilder('c')
            ->select('1')
            ->where('c.note = :note')
            ->andWhere('c.user = :user')
            ->setParameter('note', $note)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
