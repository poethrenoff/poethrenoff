<?php

namespace App\Repository;

use App\Entity\Work;
use App\Entity\WorkGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface; // Assuming Knp Paginator is used

/**
 * @extends ServiceEntityRepository<Work>
 *
 * @method Work|null find($id, $lockMode = null, $lockVersion = null)
 * @method Work|null findOneBy(array $criteria, array $orderBy = null)
 * @method Work[]    findAll()
 * @method Work[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
     * @param int $page Current page number for pagination.
     * @param int $limit Items per page.
     * @return \Knp\Component\Pager\Pagination\PaginationInterface
     */
    public function findActiveByGroup(WorkGroup $group, int $page = 1, int $limit = 10): \Knp\Component\Pager\Pagination\PaginationInterface
    {
        $qb = $this->createQueryBuilder('w')
            ->andWhere('w.group = :group')
            ->andWhere('w.isActive = :active')
            ->setParameter('group', $group)
            ->setParameter('active', true)
            ->orderBy('w.position', 'ASC');

        return $this->paginator->paginate(
            $qb,
            $page,
            $limit
        );
    }

    /**
     * Finds the previous and next active work within the same WorkGroup.
     *
     * @param Work $work The current work.
     * @return array An array containing 'prev' and 'next' Work objects, or null.
     */
    public function findPrevNext(Work $work): array
    {
        if (!$work->getGroup()) {
            return ['prev' => null, 'next' => null];
        }

        $currentPosition = $work->getPosition();
        $group = $work->getGroup();

        $qb = $this->createQueryBuilder('w');

        // Find Previous Work
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

        // Find Next Work
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
     * Hightlights matching terms.
     *
     * @param string $query The search query.
     * @param int $page Current page number.
     * @param int $limit Items per page.
     * @return \Knp\Component\Pager\Pagination\PaginationInterface
     */
    public function search(string $query, int $page = 1, int $limit = 10): \Knp\Component\Pager\Pagination\PaginationInterface
    {
        // Basic keyword splitting for AND logic.
        // A more robust solution would handle phrases, stemming, etc.
        $words = explode(' ', $query);
        $words = array_filter($words); // Remove empty elements

        $qb = $this->createQueryBuilder('w')
            ->leftJoin('w.group', 'g') // Join with group to get group title and potentially filter by active groups
            ->andWhere('w.isActive = :active') // Ensure we only search active works
            ->setParameter('active', true);

        $qb->addSelect('g'); // Select the group to avoid separate query later

        $firstWord = true;
        $parameterIndex = 0;
        $likeConditions = [];

        foreach ($words as $word) {
            // Build LIKE conditions for title and text
            $titleParam = 'word_' . $parameterIndex . '_title';
            $textParam = 'word_' . $parameterIndex . '_text';

            $likeConditions[] = " (w.title LIKE :{$titleParam} OR w.text LIKE :{$textParam}) ";

            $qb->setParameter($titleParam, '%' . $word . '%');
            $qb->setParameter($textParam, '%' . $word . '%');

            $parameterIndex++;
        }

        if (!empty($likeConditions)) {
            // Combine all LIKE conditions with AND
            $qb->andWhere(implode(' AND ', $likeConditions));
        } else {
            // If no words, return empty results or handle as needed
             $qb->andWhere('1 = 0'); // No results if query is empty
        }

        // Order by position within the group for consistency, or by relevance if possible
        // For simplicity, ordering by position and then group title.
        // A real full-text search would order by relevance score.
        $qb->orderBy('w.position', 'ASC');

        // Add snippet generation logic here if possible or handle in the controller.
        // Doctrine ORM doesn't directly support generating highlighted snippets like SQL's
        // FTS functions. This typically needs to be done in PHP after fetching results,
        // or by using a database-specific FTS extension and a custom DQL function.

        // For now, we'll return the paginated results and handle snippet generation in controller.
        // The controller will need to re-implement the search logic to generate snippets.
        // Or, we can add a placeholder and refine later.

        // Let's add an example of how one might structure the SELECT for potential snippet generation
        // but this is complex without database FTS support.
        // Example: Add fields needed for snippet generation:
        // $qb->addSelect('w.title', 'w.text');

        return $this->paginator->paginate(
            $qb,
            $page,
            $limit
        );
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
        $count = $this->createQueryBuilder('w')
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
