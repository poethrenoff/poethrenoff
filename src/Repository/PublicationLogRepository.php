<?php

namespace App\Repository;

use App\Entity\PublicationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicationLog>
 */
class PublicationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicationLog::class);
    }

    /**
     * @return list<PublicationLog>
     */
    public function findRecent(int $limit): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
