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

    public function isCollaborator(Note $note, User $user): bool
    {
        return (bool) $this->createQueryBuilder('c')
            ->select('1')
            ->where('c.note = :note')
            ->andWhere('c.user = :user')
            ->setParameters([
                'note' => $note,
                'user' => $user,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }
}
