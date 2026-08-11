<?php

namespace App\Repository;

use App\Entity\Work;
use App\Entity\WorkGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Work>
 *
 * @method Work|null find($id, $lockMode = null, $lockVersion = null)
 * @method Work|null findOneBy(mixed[] $criteria, mixed[] $orderBy = null)
 * @method Work[]    findAll()
 * @method Work[]    findBy(mixed[] $criteria, mixed[] $orderBy = null, $limit = null, $offset = null)
 */
class WorkRepository extends ServiceEntityRepository
{
    private PaginatorInterface $paginator;

    public function __construct(ManagerRegistry $registry, PaginatorInterface $paginator)
    {
        parent::__construct($registry, Work::class);
        $this->paginator = $paginator;
    }

    /**
     * Finds active works within a specific WorkGroup, sorted by position.
     *
     * @param WorkGroup $group The parent WorkGroup.
     * @return list<Work>
     */
    public function findActiveByGroup(WorkGroup $group): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.group = :group')
            ->andWhere('w.isActive = :active')
            ->setParameter('group', $group)
            ->setParameter('active', true)
            ->orderBy('w.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds the previous and next active work within the same WorkGroup.
     *
     * @param Work $work The current work.
     * @return array{prev: Work|null, next: Work|null}
     */
    public function findPrevNext(Work $work): array
    {
        if (!$work->getGroup()) {
            return ['prev' => null, 'next' => null];
        }

        $currentPosition = $work->getPosition();
        $group = $work->getGroup();

        $qb = $this->createQueryBuilder('w');

        $prevQb = clone $qb;
        $prevQb
            ->andWhere('w.group = :group')
            ->andWhere('w.isActive = :active')
            ->andWhere('w.position < :currentPosition')
            ->setParameter('group', $group)
            ->setParameter('active', true)
            ->setParameter('currentPosition', $currentPosition)
            ->orderBy('w.position', 'DESC')
            ->setMaxResults(1);

        $nextQb = clone $qb;
        $nextQb
            ->andWhere('w.group = :group')
            ->andWhere('w.isActive = :active')
            ->andWhere('w.position > :currentPosition')
            ->setParameter('group', $group)
            ->setParameter('active', true)
            ->setParameter('currentPosition', $currentPosition)
            ->orderBy('w.position', 'ASC')
            ->setMaxResults(1);

        return [
            'prev' => $prevQb->getQuery()->getOneOrNullResult(),
            'next' => $nextQb->getQuery()->getOneOrNullResult(),
        ];
    }

    /**
     * Performs a full-text search across active works.
     * Splits the query into words and applies AND logic.
     *
     * @param string $query The search query.
     * @param int $page Current page number.
     * @param int $limit Items per page.
     * @return PaginationInterface<int, Work>
     */
    public function search(
        string $query,
        int $page = 1,
        int $limit = 10,
        bool $favoritesOnly = false,
    ): PaginationInterface {
        $words = explode(' ', $query);
        $words = array_filter($words);

        $qb = $this->createQueryBuilder('w')
            ->leftJoin('w.group', 'g')
            ->andWhere('w.isActive = :active')
            ->setParameter('active', true);

        if ($favoritesOnly) {
            $qb->andWhere('g.isFavorite = :favorite')
                ->setParameter('favorite', true);
        }

        $qb->addSelect('g');

        $parameterIndex = 0;
        $likeConditions = [];

        foreach ($words as $word) {
            $titleParam = 'word_' . $parameterIndex . '_title';
            $textParam = 'word_' . $parameterIndex . '_text';

            $likeConditions[] = " (w.title LIKE :{$titleParam} OR w.text LIKE :{$textParam}) ";

            $qb->setParameter($titleParam, '%' . $word . '%');
            $qb->setParameter($textParam, '%' . $word . '%');

            $parameterIndex++;
        }

        if (!empty($likeConditions)) {
            $qb->andWhere(implode(' AND ', $likeConditions));
        } else {
             $qb->andWhere('1 = 0');
        }

        $qb->orderBy('w.position', 'ASC');

        /** @var PaginationInterface<int, Work> $pagination */
        $pagination = $this->paginator->paginate(
            $qb,
            $page,
            $limit
        );

        return $pagination;
    }

    /**
     * Helper to get a single active work by ID, ensuring it's active.
     * Used when viewing a specific work.
     */
    public function findOneActiveById(int $id): ?Work
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.id = :id')
            ->andWhere('w.isActive = :active')
            ->setParameter('id', $id)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Finds a random active work.
     * Efficiently fetches a random record by using a random offset based on total count.
     */
    public function findRandomActiveFromFavorites(): ?Work
    {
        $count = (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->innerJoin('w.group', 'g')
            ->andWhere('w.isActive = :active')
            ->andWhere('g.isFavorite = :favorite')
            ->setParameter('active', true)
            ->setParameter('favorite', true)
            ->getQuery()
            ->getSingleScalarResult();

        if ($count == 0) {
            return null;
        }

        $offset = max(0, rand(0, $count - 1));

        return $this->createQueryBuilder('w')
            ->innerJoin('w.group', 'g')
            ->andWhere('w.isActive = :active')
            ->andWhere('g.isFavorite = :favorite')
            ->setParameter('active', true)
            ->setParameter('favorite', true)
            ->setFirstResult($offset)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
