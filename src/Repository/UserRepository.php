<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null        find($id, $lockMode = null, $lockVersion = null)
 * @method User|null        findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method array<int, User> findAll()
 * @method array<int, User> findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findIdByUuid(string $uuid): ?int
    {
        try {
            $id = $this->createQueryBuilder('u')
                ->select('u.id')
                ->andWhere('u.uuid = :uuid')
                ->setParameter('uuid', $uuid)
                ->getQuery()
                ->getSingleScalarResult();

            return (int) $id;
        } catch (NoResultException) {
            return null;
        }
    }

    public function findOneByEmailCaseInsensitive(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = LOWER(:email)')
            ->setParameter('email', mb_trim($email))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
