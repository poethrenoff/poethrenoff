<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Monster;
use App\Repository\MonsterRepository;
use App\Service\MonsterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:monster:update',
    description: 'Обновляет рейтинг авторов Стихи.ру по количеству произведений',
)]
class MonsterUpdateCommand extends Command
{
    public function __construct(
        private MonsterRepository $monsterRepository,
        private MonsterService $monsterService,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('login', null, InputOption::VALUE_REQUIRED, 'Обновить только автора с указанным логином')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Обновить только первых N авторов')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показать план без записи в БД')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $login = $input->getOption('login');
        $limitOption = $input->getOption('limit');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($limitOption !== null && ((int) $limitOption < 1)) {
            $io->error('Значение --limit должно быть положительным числом');

            return Command::FAILURE;
        }

        $monsters = $this->resolveMonsters($login, $limitOption !== null ? (int) $limitOption : null, $io);
        if ($monsters === []) {
            return Command::FAILURE;
        }

        $io->section(sprintf(
            'Обновление рейтинга (%s), авторов: %d',
            $dryRun ? 'dry-run' : 'запись в БД',
            count($monsters),
        ));

        $updated = 0;
        $failed = 0;

        foreach ($monsters as $monster) {
            $label = str_pad($monster->getLogin(), 20);

            try {
                $data = $this->monsterService->fetchAuthorData($monster->getLogin());
            } catch (\RuntimeException $e) {
                $failed++;
                $io->writeln(sprintf('<error>%s</error> %s', $label, $e->getMessage()));

                if (!$dryRun) {
                    $monster->setIsActive(false);
                }

                continue;
            }

            $updated++;
            $io->writeln(sprintf('<info>%s</info> %s — %d', $label, $data['author'], $data['poems']));

            if (!$dryRun) {
                $monster->setPoemsOld($monster->getPoems());
                $monster->setPoems($data['poems']);
                $monster->setAuthor($data['author']);
                $monster->setLastVisitDate($data['lastVisitDate']);
                $monster->setIsActive(true);
            }
        }

        if (!$dryRun) {
            $this->monsterService->recalculatePlaces($monsters);
            $this->entityManager->flush();
        }

        $io->newLine();
        $io->writeln(sprintf('Обновлено: %d, ошибок: %d', $updated, $failed));

        if ($failed > 0) {
            $io->warning('Часть авторов недоступна и помечена как неактивные');
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<Monster>
     */
    private function resolveMonsters(?string $login, ?int $limit, SymfonyStyle $io): array
    {
        if ($login !== null) {
            $monster = $this->monsterRepository->findOneBy(['login' => $login]);
            if ($monster === null) {
                $io->error(sprintf('Автор с логином "%s" не найден в таблице monster', $login));

                return [];
            }

            return [$monster];
        }

        $monsters = $this->monsterRepository->findOrderedByPoems();

        if ($limit !== null) {
            $monsters = array_slice($monsters, 0, $limit);
        }

        return $monsters;
    }
}
