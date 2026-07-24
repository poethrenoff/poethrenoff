<?php

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function findTagCloud(int $limit): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t', 'COUNT(p.id) AS cnt')
            ->join('t.posts', 'p')
            ->groupBy('t.id')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults($limit);
        return $qb->getQuery()->getResult();
    }
}
