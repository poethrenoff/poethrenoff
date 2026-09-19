<?php

namespace App\Command;

use App\Entity\Work;
use App\Entity\WorkGroup;
use App\Enum\PoemStatus;
use App\Repository\PoemRepository;
use App\Repository\WorkGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:transfer:poems',
    description: 'Transfers poems from the Мастерская feed to the site as a new WorkGroup',
)]
class TransferPoemsToSiteCommand extends Command
{
    public function __construct(
        private PoemRepository $poemRepository,
        private WorkGroupRepository $workGroupRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $poems = $this->poemRepository->findByStatus(PoemStatus::Draft);

        if ($poems === []) {
            $io->warning('В Мастерской нет стихов для переноса');

            return Command::SUCCESS;
        }

        $title = (string) $io->ask(
            'Название нового раздела',
            null,
            static function (?string $value): string {
                $value = trim((string) $value);

                if ($value === '') {
                    throw new \RuntimeException('Название раздела не может быть пустым');
                }

                return $value;
            }
        );

        if ($title === '') {
            $io->error('Название раздела не может быть пустым');

            return Command::FAILURE;
        }

        $rawComment = $io->ask('Комментарий к разделу (необязательно)');
        $comment = is_string($rawComment) && trim($rawComment) !== ''
            ? trim($rawComment)
            : null;

        $lastFavorite = $this->workGroupRepository->findLastAddedFavorite();

        if ($lastFavorite === null) {
            $io->warning(
                'Не найдено ни одного раздела с галочкой is_favorite — '
                . 'раздел будет создан в корне с ближайшей свободной позицией'
            );

            $parent = null;
            $position = $this->workGroupRepository->findNextPosition();
        } else {
            $parent = $lastFavorite->getParent();
            $position = $lastFavorite->getPosition() - 1.0;
        }

        $first = $poems[0];
        $last = $poems[count($poems) - 1];

        $io->newLine();
        $io->section('Итоги переноса');
        $io->listing([
            sprintf('Стихов к переносу: %d', count($poems)),
            sprintf('Первый в ленте: %s', $first->getTitle() ?? $first->getDisplayTitle()),
            sprintf('Последний в ленте: %s', $last->getTitle() ?? $last->getDisplayTitle()),
            sprintf('Раздел: %s', $title),
            sprintf('Комментарий раздела: %s', $comment ?? '—'),
            sprintf('Родительский раздел: %s', $parent?->getTitle() ?? '— (корень)'),
            sprintf('Позиция раздела: %g', $position),
        ]);

        if (!$io->confirm('Создать раздел и перенести стихи?', true)) {
            $io->warning('Перенос отменён');

            return Command::SUCCESS;
        }

        try {
            $this->entityManager->wrapInTransaction(
                function () use ($poems, $title, $comment, $parent, $position): void {
                    $group = new WorkGroup();
                    $group->setTitle($title);
                    $group->setComment($comment);
                    $group->setParent($parent);
                    $group->setPosition($position);
                    $group->setIsFavorite(true);

                    $this->entityManager->persist($group);

                    foreach ($poems as $index => $poem) {
                        $work = new Work();
                        $work->setGroup($group);
                        $work->setTitle($poem->getTitle() ?? '');
                        $work->setText($poem->getContent());
                        $work->setComment($poem->getComment()?->format('d.m.Y') ?? '');
                        $work->setPosition((float) ($index + 1));

                        $this->entityManager->persist($work);
                    }

                    foreach ($poems as $poem) {
                        $this->entityManager->remove($poem);
                    }

                    $this->entityManager->flush();
                }
            );
        } catch (\Throwable $e) {
            $io->error(sprintf('Ошибка при переносе: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Раздел «%s» создан, перенесено стихов: %d, исходные стихи удалены из Мастерской',
            $title,
            count($poems)
        ));

        return Command::SUCCESS;
    }
}
