<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Note;
use App\Entity\User;
use App\Entity\NoteVisibility;
use App\Query\Note\ListNotesQuery;
use App\Query\Note\PublicNotesQuery;
use App\Repository\Result\PaginatedResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
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

    public function findOneByIdAndOwner(int $id, User $owner): ?Note
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.id = :id')
            ->andWhere('n.owner = :owner')
            ->setParameter('id', $id)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isOwnedBy(int $noteId, User $owner): bool
    {
        return (bool) $this->createQueryBuilder('n')
            ->select('1')
            ->andWhere('n.id = :id')
            ->andWhere('n.owner = :owner')
            ->setParameter('id', $noteId)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getOneOrNullResult();
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

    public function findPaginatedForOwnerWithFilters(ListNotesQuery $query): PaginatedResult
    {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        $search = $query->q !== null ? trim($query->q) : null;
        $dbalFilters = $conn->createQueryBuilder()
            ->from('notes', 'n')
            ->where('n.owner_id = :ownerId')
            ->setParameter('ownerId', $query->ownerId, Types::INTEGER);

        if ($search !== null && $search !== '') {
            $dbalFilters
                ->andWhere("(n.search_vector_simple @@ plainto_tsquery('simple', :q) OR n.title ILIKE :pattern OR n.description ILIKE :pattern)")
                ->setParameter('q', $search, Types::STRING)
                ->setParameter('pattern', '%' . $search . '%', Types::STRING);
        }

        if ($query->labels !== []) {
            $labelsLiteral = $this->toPgTextArrayLiteral($query->labels);
            $dbalFilters
                ->andWhere('n.labels && :labels')
                ->setParameter('labels', $labelsLiteral, Types::STRING);
        }

        $totalQb = clone $dbalFilters;
        $total = (int) $totalQb
            ->select('COUNT(*)')
            ->executeQuery()
            ->fetchOne();

        $idsQb = clone $dbalFilters;
        $ids = $idsQb
            ->select('n.id')
            ->orderBy('n.created_at', 'DESC')
            ->setMaxResults($query->perPage)
            ->setFirstResult(($query->page - 1) * $query->perPage)
            ->executeQuery()
            ->fetchFirstColumn();

        if ($ids === []) {
            return new PaginatedResult([], $total);
        }

        /** @var list<Note> $items */
        $items = $this->createQueryBuilder('n')
            ->andWhere('n.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return new PaginatedResult($items, $total);
    }

    public function findPublicNotesForOwner(PublicNotesQuery $query): PaginatedResult
    {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        $search = $query->search !== null ? trim($query->search) : null;
        $filters = $conn->createQueryBuilder()
            ->from('notes', 'n')
            ->where('n.owner_id = :ownerId')
            ->andWhere('n.visibility = :visibility')
            ->setParameter('ownerId', $query->ownerId, Types::INTEGER)
            ->setParameter('visibility', NoteVisibility::Public->value, Types::STRING);

        if ($search !== null && $search !== '') {
            $filters
                ->andWhere("(n.search_vector_simple @@ plainto_tsquery('simple', :q) OR n.title ILIKE :pattern OR n.description ILIKE :pattern)")
                ->setParameter('q', $search, Types::STRING)
                ->setParameter('pattern', '%' . $search . '%', Types::STRING);
        }

        if ($query->labels !== []) {
            $labelsLiteral = $this->toPgTextArrayLiteral($query->labels);
            $filters
                ->andWhere('n.labels && :labels')
                ->setParameter('labels', $labelsLiteral, Types::STRING);
        }

        $total = (int) (clone $filters)
            ->select('COUNT(*)')
            ->executeQuery()
            ->fetchOne();

        $ids = (clone $filters)
            ->select('n.id')
            ->orderBy('n.created_at', 'DESC')
            ->setMaxResults($query->perPage)
            ->setFirstResult(($query->page - 1) * $query->perPage)
            ->executeQuery()
            ->fetchFirstColumn();

        if ($ids === []) {
            return new PaginatedResult([], $total);
        }

        /** @var list<Note> $items */
        $items = $this->createQueryBuilder('n')
            ->andWhere('n.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return new PaginatedResult($items, $total);
    }

    /**
     * @param list<string> $labels
     */
    private function toPgTextArrayLiteral(array $labels): string
    {
        $escaped = array_map(
            static fn(string $label): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $label) . '"',
            $labels
        );

        return '{' . implode(',', $escaped) . '}';
    }
}
