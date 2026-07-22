<?php

namespace App\Repository;

use App\Entity\WorkGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkGroup>
 *
 * @method WorkGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method WorkGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method WorkGroup[]    findAll()
 * @method WorkGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WorkGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkGroup::class);
    }

    public function findAllActiveSorted(): array
    {
        return $this->createQueryBuilder('wg')
            ->andWhere('wg.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('wg.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

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
