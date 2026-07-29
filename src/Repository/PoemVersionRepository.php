<?php

namespace App\Repository;

use App\Entity\PoemVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PoemVersion>
 */
class PoemVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PoemVersion::class);
    }

    public function findNextVersion(PoemVersion $version): ?PoemVersion
    {
        return $this->createQueryBuilder('v')
            ->where('v.poem = :poem')
            ->andWhere('v.createdAt > :createdAt')
            ->setParameter('poem', $version->getPoem())
            ->setParameter('createdAt', $version->getCreatedAt())
            ->orderBy('v.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
