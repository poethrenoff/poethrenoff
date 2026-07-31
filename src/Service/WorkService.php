<?php

namespace App\Service;

use App\Entity\Poem;
use App\Enum\PoemStatus;
use App\Repository\PoemRepository;
use Doctrine\ORM\EntityManagerInterface;

class WorkService
{
    public function __construct(
        private PoemRepository $poemRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function parseCommentDate(?string $value): ?\DateTimeImmutable
    {
        if (!$value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!d.m.Y', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($date && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date;
        }

        return null;
    }

    public function reorderPoem(Poem $poem, ?int $beforeId, ?int $afterId): void
    {
        $before = $beforeId !== null ? $this->poemRepository->find($beforeId) : null;
        $after = $afterId !== null ? $this->poemRepository->find($afterId) : null;

        if ($before !== null && $after !== null) {
            $poem->setPosition(($before->getPosition() + $after->getPosition()) / 2);
        } elseif ($before !== null) {
            $poem->setPosition($before->getPosition() - 1.0);
        } elseif ($after !== null) {
            $poem->setPosition($after->getPosition() + 1.0);
        } else {
            $poem->setPosition($this->poemRepository->findNextPosition());
        }

        $this->entityManager->flush();
    }

    public function createVersion(Poem $poem, Poem $original): void
    {
        $version = $poem->createVersion($original);
        $this->entityManager->persist($version);
        $this->entityManager->flush();
    }

    public function trashPoem(Poem $poem): void
    {
        $poem->trash();
        $this->entityManager->flush();
    }

    public function restorePoem(Poem $poem): void
    {
        $poem->restore();
        $this->entityManager->flush();
    }

    public function deletePoem(Poem $poem): void
    {
        $this->entityManager->remove($poem);
        $this->entityManager->flush();
    }

    public function isPoemTrash(Poem $poem): bool
    {
        return $poem->getStatus() === PoemStatus::Trash;
    }
}
