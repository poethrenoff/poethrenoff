<?php

namespace App\Repository;

use App\Entity\Poem;
use App\Enum\PoemStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Poem>
 */
class PoemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Poem::class);
    }

    public function findNextPosition(): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('MAX(p.position)')
            ->where('p.status != :trash')
            ->setParameter('trash', PoemStatus::Trash)
            ->getQuery()
            ->getSingleScalarResult() + 1.0;
    }

    /**
     * @return list<Poem>
     */
    public function findByStatus(PoemStatus|string $status): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status != :trash')
            ->setParameter('trash', PoemStatus::Trash)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(PoemStatus|string $status): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findStreak(): int
    {
        $days = $this->getSortedDraftDays();
        if (!$days) {
            return 0;
        }

        $today = (int)(strtotime('today UTC') / 86400);

        $streak = 0;
        $expected = $today;

        // Идем с конца отсортированного списка дней
        for ($i = count($days) - 1; $i >= 0; $i--) {
            if ($days[$i] === $expected) {
                $streak++;
                $expected--;
            } elseif ($days[$i] < $expected) {
                // Если текущий день меньше ожидаемого — серия прервалась
                break;
            }
        }

        return $streak;
    }

    public function findMaxStreak(): int
    {
        $days = $this->getSortedDraftDays();
        if (!$days) {
            return 0;
        }

        $maxStreak = 1;
        $currentStreak = 1;

        for ($i = 1; $i < count($days); $i++) {
            if ($days[$i] === $days[$i - 1] + 1) {
                $currentStreak++;
                $maxStreak = max($maxStreak, $currentStreak);
            } else {
                $currentStreak = 1;
            }
        }

        return $maxStreak;
    }

    /**
     * @return int[] Unique sorted days (as days since epoch)
     */
    private function getSortedDraftDays(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.comment as day')
            ->where('p.comment IS NOT NULL')
            ->andWhere('p.status = :status')
            ->setParameter('status', PoemStatus::Draft)
            ->getQuery()
            ->getArrayResult();

        $days = [];
        foreach ($rows as $row) {
            $days[] = (int) (strtotime($row['day']->format('Y-m-d') . ' UTC') / 86400);
        }

        $days = array_unique($days);
        sort($days);

        return $days;
    }
}
