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

    /**
     * @return list<Poem>
     */
    public function findActiveOrderedByPosition(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status != :trash')
            ->setParameter('trash', PoemStatus::Trash)
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

    /**
     * @return list<Poem>
     */
    public function findForSidebar(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status != :trash')
            ->setParameter('trash', PoemStatus::Trash)
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findNextPosition(float $afterPosition): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('MAX(p.position)')
            ->where('p.position > :after')
            ->setParameter('after', $afterPosition)
            ->getQuery()
            ->getSingleScalarResult() + 1.0;
    }

    public function findFirstPosition(): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('MIN(p.position)')
            ->where('p.status != :trash')
            ->setParameter('trash', PoemStatus::Trash)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (float) $result : 0.0;
    }

    /**
     * @return list<Poem>
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(string $status): int
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

        // Текущий день в формате индекса (дней с начала эпохи)
        $today = (int)(strtotime((new \DateTimeImmutable('today'))->format('Y-m-d') . ' UTC') / 86400);

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
        $comments = $this->createQueryBuilder('p')
            ->select('DISTINCT p.comment')
            ->where('p.comment IS NOT NULL')
            ->andWhere('p.status = :status')
            ->setParameter('status', PoemStatus::Draft->value)
            ->getQuery()
            ->getSingleColumnResult();

        $days = [];
        foreach ($comments as $c) {
            // Ожидаемый формат DD.MM.YYYY
            if (strlen($c) === 10 && preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $c)) {
                $d = (int)substr($c, 0, 2);
                $m = (int)substr($c, 3, 2);
                $y = (int)substr($c, 6, 4);
                if (checkdate($m, $d, $y)) {
                    // Используем UTC для получения консистентного номера дня
                    $days[] = (int)(gmmktime(0, 0, 0, $m, $d, $y) / 86400);
                }
            }
        }

        if (!$days) {
            return [];
        }

        $days = array_unique($days);
        sort($days);

        return $days;
    }
}
