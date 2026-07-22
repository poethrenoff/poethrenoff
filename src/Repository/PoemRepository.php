<?php

namespace App\Repository;

use App\Entity\Poem;
use App\Enum\PoemStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Poem>
 */
class PoemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Poem::class);
    }

    /**
     * @return list<Poem>
     */
    public function findActiveOrderedByPosition(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status != :trash')
            ->setParameter('trash', PoemStatus::Trash)
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status != :trash')
            ->setParameter('trash', PoemStatus::Trash)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Poem>
     */
    public function findForSidebar(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status != :trash')
            ->setParameter('trash', PoemStatus::Trash)
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findNextPosition(float $afterPosition): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('MAX(p.position)')
            ->where('p.position > :after')
            ->setParameter('after', $afterPosition)
            ->getQuery()
            ->getSingleScalarResult() + 1.0;
    }

    public function findFirstPosition(): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('MIN(p.position)')
            ->where('p.status != :trash')
            ->setParameter('trash', PoemStatus::Trash)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (float) $result : 0.0;
    }

    /**
     * @return list<Poem>
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
