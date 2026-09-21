<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\WorkGroupRepository;
use App\Repository\WorkRepository;
use App\Service\StihiRuService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:stihiru:publish',
    description: 'Publishes the newest favorite collection to stihi.ru in reverse order',
)]
class PublishToStihiRuCommand extends Command
{
    private const int DEFAULT_LIMIT = 20;

    public function __construct(
        private WorkGroupRepository $groupRepository,
        private WorkRepository $workRepository,
        private StihiRuService $stihiRuService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Max works to publish in one run',
                (string) self::DEFAULT_LIMIT,
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report the publishing plan without posting')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($limit < 1) {
            $io->error('Значение --limit должно быть положительным числом');

            return Command::FAILURE;
        }

        if (!$this->stihiRuService->isConfigured()) {
            $io->error('Не заданы STIHIRU_LOGIN / STIHIRU_PASSWORD в .env');

            return Command::FAILURE;
        }

        $groups = $this->groupRepository->findFavoriteActiveSorted();
        if ($groups === []) {
            $io->error('Нет ни одного избранного раздела для публикации');

            return Command::FAILURE;
        }

        $group = $groups[0];
        $works = array_reverse($this->workRepository->findActiveByGroup($group));
        if ($works === []) {
            $io->warning(sprintf('В разделе «%s» нет активных произведений', $group->getTitle()));

            return Command::FAILURE;
        }

        try {
            $this->stihiRuService->login();
            $destination = $this->stihiRuService->findDestinationCollection();
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $candidates = array_slice($works, $destination['offset'], $limit);
        if ($candidates === []) {
            $io->info('Все произведения сборника уже опубликованы');

            return Command::SUCCESS;
        }

        $io->section(sprintf(
            'Раздел: «%s», сборник на сайте: #%d (опубликовано: %d, будет опубликовано: %d)',
            $group->getTitle(),
            $destination['bookId'],
            $destination['offset'],
            count($candidates),
        ));

        if ($dryRun) {
            foreach ($candidates as $index => $work) {
                $io->writeln(sprintf('%2d. [DRY-RUN] %s', $index + 1, $work->getTitle()));
            }
            $io->success(sprintf('Будет опубликовано произведений: %d', count($candidates)));

            return Command::SUCCESS;
        }

        $published = 0;
        $failed = 0;

        foreach ($candidates as $work) {
            try {
                $url = $this->stihiRuService->publishWork($work, $destination['bookId']);
                $published++;
                $io->writeln(sprintf('[OK] %s -> %s', $work->getTitle(), $url));
            } catch (\RuntimeException $e) {
                $failed++;
                $io->writeln(sprintf('<error>[ERROR]</error> %s: %s', $work->getTitle(), $e->getMessage()));
            }
        }

        if ($failed > 0) {
            $io->warning(sprintf('Опубликовано: %d, ошибок: %d', $published, $failed));

            return Command::FAILURE;
        }

        $io->success(sprintf('Опубликовано произведений: %d', $published));

        return Command::SUCCESS;
    }
}
