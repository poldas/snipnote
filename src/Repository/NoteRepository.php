<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Note;
use App\Entity\NoteVisibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Note>
 *
 * @method Note|null find($id, $lockMode = null, $lockVersion = null)
 * @method Note|null findOneBy(array $criteria, array $orderBy = null)
 * @method array<int, Note> findAll()
 * @method array<int, Note> findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }

    public function findOneByUrlToken(string $token): ?Note
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.urlToken = :token')
            ->andWhere('n.visibility = :visibility')
            ->setParameter('token', $token)
            ->setParameter('visibility', NoteVisibility::Public)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
