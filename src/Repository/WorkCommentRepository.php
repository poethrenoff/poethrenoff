<?php

namespace App\Repository;

use App\Entity\WorkComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\Work;

/**
 * @extends ServiceEntityRepository<WorkComment>
 */
class WorkCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkComment::class);
    }

    public function findActiveByWork(Work $work): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.work = :work')
            ->andWhere('c.isActive = :active')
            ->setParameter('work', $work)
            ->setParameter('active', true)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
