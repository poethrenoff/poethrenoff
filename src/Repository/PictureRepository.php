<?php

namespace App\Repository;

use App\Entity\Picture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface; // Assuming Knp Paginator is used

/**
 * @extends ServiceEntityRepository<Picture>
 *
 * @method Picture|null find($id, $lockMode = null, $lockVersion = null)
 * @method Picture|null findOneBy(array $criteria, array $orderBy = null)
 * @method Picture[]    findAll()
 * @method Picture[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
     * @return \Knp\Component\Pager\Pagination\PaginationInterface
     */
    public function findActivePaginated(int $page = 1, int $limit = 24): \Knp\Component\Pager\Pagination\PaginationInterface
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->orderBy('p.date', 'DESC')
            ->setParameter('active', true);

        return $this->paginator->paginate(
            $qb,
            $page,
            $limit
        );
    }
}
