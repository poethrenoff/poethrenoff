<?php

namespace App\Repository;

use App\Entity\BlogPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<BlogPost>
 */
class BlogPostRepository extends ServiceEntityRepository
{
    private PaginatorInterface $paginator;

    public function __construct(ManagerRegistry $registry, PaginatorInterface $paginator)
    {
        parent::__construct($registry, BlogPost::class);
        $this->paginator = $paginator;
    }

    public function findActivePaginated(int $page, int $limit): PaginationInterface
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.publishedAt', 'DESC');

        return $this->paginator->paginate($qb, $page, $limit);
    }

    public function findOneActiveById(int $id): ?BlogPost
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.id = :id')
            ->andWhere('p.isActive = :active')
            ->setParameter('id', $id)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function searchByText(string $query, int $page, int $limit): PaginationInterface
    {
        $words = explode(' ', $query);
        $words = array_filter($words);

        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true);

        $likeConditions = [];
        $parameterIndex = 0;
        foreach ($words as $word) {
            $param = 'word_' . $parameterIndex;
            $likeConditions[] = "p.content LIKE :$param";
            $qb->setParameter($param, '%' . $word . '%');
            $parameterIndex++;
        }

        if (!empty($likeConditions)) {
            $qb->andWhere(implode(' AND ', $likeConditions));
        } else {
            $qb->andWhere('1 = 0');
        }

        $qb->orderBy('p.publishedAt', 'DESC');

        return $this->paginator->paginate($qb, $page, $limit);
    }

    public function findActiveByTagPaginated(string $tagTitle, int $page, int $limit): PaginationInterface
    {
        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.tags', 't')
            ->andWhere('p.isActive = :active')
            ->andWhere('t.title = :tagTitle')
            ->setParameter('active', true)
            ->setParameter('tagTitle', $tagTitle)
            ->orderBy('p.publishedAt', 'DESC');

        return $this->paginator->paginate($qb, $page, $limit);
    }
}
