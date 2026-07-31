<?php

namespace App\Repository;

use App\Entity\Picture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Picture>
 *
 * @method Picture|null find($id, $lockMode = null, $lockVersion = null)
 * @method Picture|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method Picture[]    findAll()
 * @method Picture[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
class PictureRepository extends ServiceEntityRepository
{
    private PaginatorInterface $paginator;

    public function __construct(ManagerRegistry $registry, PaginatorInterface $paginator)
    {
        parent::__construct($registry, Picture::class);
        $this->paginator = $paginator;
    }

    /**
     * Finds all active pictures with pagination.
     *
     * @param int $page Current page number.
     * @param int $limit Items per page.
     * @return PaginationInterface<int, Picture>
     */
    public function findActivePaginated(int $page = 1, int $limit = 24): PaginationInterface
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->orderBy('p.date', 'DESC')
            ->setParameter('active', true);

        /** @var PaginationInterface<int, Picture> $pagination */
        $pagination = $this->paginator->paginate(
            $qb,
            $page,
            $limit
        );

        return $pagination;
    }
}
