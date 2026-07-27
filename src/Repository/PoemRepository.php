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
        $comments = $this->createQueryBuilder('p')
            ->select('p.comment')
            ->where('p.comment IS NOT NULL')
            ->andWhere('p.status = :status')
            ->setParameter('status', PoemStatus::Draft)
            ->getQuery()
            ->getScalarResult();

        $dates = [];
        foreach ($comments as $row) {
            $c = $row['comment'];
            if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $c)) {
                continue;
            }
            $d = \DateTimeImmutable::createFromFormat('d.m.Y', $c);
            if ($d && $d->format('d.m.Y') === $c) {
                $dates[] = $d->format('Y-m-d');
            }
        }

        $dates = array_unique($dates);
        if (!$dates) {
            return 0;
        }

        $today = new \DateTimeImmutable('today');
        $datesSet = array_flip($dates);

        $streak = 0;
        $day = $today;
        while (isset($datesSet[$day->format('Y-m-d')])) {
            $streak++;
            $day = $day->modify('-1 day');
        }

        return $streak;
    }

    public function findMaxStreak(): int
    {
        $comments = $this->createQueryBuilder('p')
            ->select('p.comment')
            ->where('p.comment IS NOT NULL')
            ->andWhere('p.status = :status')
            ->setParameter('status', PoemStatus::Draft)
            ->getQuery()
            ->getScalarResult();

        $dates = [];
        foreach ($comments as $row) {
            $c = $row['comment'];
            if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $c)) {
                continue;
            }
            $d = \DateTimeImmutable::createFromFormat('d.m.Y', $c);
            if ($d && $d->format('d.m.Y') === $c) {
                $dates[] = $d->format('Y-m-d');
            }
        }

        $dates = array_unique($dates);
        if (!$dates) {
            return 0;
        }

        sort($dates);

        $maxStreak = 1;
        $currentStreak = 1;

        for ($i = 1; $i < count($dates); $i++) {
            $prev = new \DateTimeImmutable($dates[$i - 1]);
            $curr = new \DateTimeImmutable($dates[$i]);
            if ($curr->diff($prev)->days === 1) {
                $currentStreak++;
                $maxStreak = max($maxStreak, $currentStreak);
            } else {
                $currentStreak = 1;
            }
        }

        return $maxStreak;
    }
}
