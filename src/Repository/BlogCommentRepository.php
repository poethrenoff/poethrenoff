<?php

namespace App\Repository;

use App\Entity\BlogComment;
use App\Entity\BlogPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogComment>
 */
class BlogCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogComment::class);
    }

    public function findActiveByPost(BlogPost $post): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.children', 'child')
            ->addSelect('child')
            ->andWhere('c.post = :post')
            ->andWhere('c.isActive = :active')
            ->setParameter('post', $post)
            ->setParameter('active', true)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
