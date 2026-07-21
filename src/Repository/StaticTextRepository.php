<?php

namespace App\Repository;

use App\Entity\StaticText;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StaticText>
 *
 * @method StaticText|null find($id, $lockMode = null, $lockVersion = null)
 * @method StaticText|null findOneBy(array $criteria, array $orderBy = null)
 * @method StaticText[]    findAll()
 * @method StaticText[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StaticTextRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StaticText::class);
    }

    /**
     * Finds a single StaticText entity by its unique slug.
     *
     * @param string $slug The slug to search for.
     * @return StaticText|null
     */
    public function findOneBySlug(string $slug): ?StaticText
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
