<?php

namespace App\Repository;

use App\Entity\WorkGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkGroup>
 *
 * @method WorkGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method WorkGroup|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method WorkGroup[]    findAll()
 * @method WorkGroup[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
class WorkGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkGroup::class);
    }

    public function findNextPosition(): float
    {
        return (float) $this->createQueryBuilder('wg')
            ->select('MAX(wg.position)')
            ->getQuery()
            ->getSingleScalarResult() + 1.0;
    }

    public function findLastAddedFavorite(): ?WorkGroup
    {
        return $this->createQueryBuilder('wg')
            ->where('wg.isFavorite = :favorite')
            ->setParameter('favorite', true)
            ->orderBy('wg.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<WorkGroup>
     */
    public function findAllActiveSorted(): array
    {
        return $this->createQueryBuilder('wg')
            ->andWhere('wg.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('wg.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<WorkGroup>
     */
    public function findFavoriteActiveSorted(): array
    {
        return $this->createQueryBuilder('wg')
            ->andWhere('wg.isActive = :active')
            ->andWhere('wg.isFavorite = :favorite')
            ->setParameter('active', true)
            ->setParameter('favorite', true)
            ->orderBy('wg.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
