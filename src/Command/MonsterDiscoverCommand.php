<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\MonsterDiscoveryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:monster:discover',
    description: 'Находит новых «монстров» Стихи.ру (авторов с ≥5000 произведений) по потоку публикаций за период',
)]
class MonsterDiscoverCommand extends Command implements SignalableCommandInterface
{
    private const int DEFAULT_CONCURRENCY = 12;
    private const int DEFAULT_RECHECK_DAYS = 90;

    private int $signals = 0;

    public function __construct(
        private MonsterDiscoveryService $discoveryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'from',
                null,
                InputOption::VALUE_REQUIRED,
                'Начало периода (YYYY-MM-DD), по умолчанию 1 января текущего года',
            )
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Конец периода (YYYY-MM-DD), по умолчанию сегодня')
            ->addOption(
                'concurrency',
                null,
                InputOption::VALUE_REQUIRED,
                'Максимум одновременных запросов к stihi.ru',
                (string) self::DEFAULT_CONCURRENCY,
            )
            ->addOption(
                'recheck-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Пропускать авторов, проверенных менее N дней назад',
                (string) self::DEFAULT_RECHECK_DAYS,
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Только посчитать и показать план, ничего не сохранять',
            )
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Сбросить состояние сканирования перед запуском')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $from = $this->resolveFrom($input->getOption('from'), $io);
        $to = $this->resolveTo($input->getOption('to'), $io);
        if ($from === null || $to === null) {
            return Command::FAILURE;
        }

        if ($from > $to) {
            $io->error(sprintf(
                'Период задан некорректно: начало %s позже конца %s',
                $from->format('Y-m-d'),
                $to->format('Y-m-d'),
            ));

            return Command::FAILURE;
        }

        $concurrency = (int) $input->getOption('concurrency');
        if ($concurrency < 1) {
            $io->error('Значение --concurrency должно быть положительным числом');

            return Command::FAILURE;
        }

        $recheckDays = (int) $input->getOption('recheck-days');
        if ($recheckDays < 0) {
            $io->error('Значение --recheck-days не может быть отрицательным');

            return Command::FAILURE;
        }

        $io->title('Поиск новых монстров Стихи.ру');
        $io->writeln(sprintf(
            'Период: %s … %s, параллельность: %d, повторная проверка: %d дней, режим: %s',
            $from->format('d.m.Y'),
            $to->format('d.m.Y'),
            $concurrency,
            $recheckDays,
            $dryRun ? 'dry-run' : 'запись в БД',
        ));

        $result = $this->discoveryService->discover(
            $from,
            $to,
            $concurrency,
            $recheckDays,
            $dryRun,
            (bool) $input->getOption('reset'),
            static function (string $message) use ($io): void {
                $io->write("\r" . $message . "\033[K");
            },
        );

        $io->newLine();
        $io->section('Итоги');

        $rows = [];
        foreach ($result['newMonsters'] as $index => $monster) {
            $rows[] = [
                $index + 1,
                $monster->getLogin(),
                $monster->getAuthor(),
                $monster->getPoems(),
            ];
        }

        if ($rows !== []) {
            $io->table(['#', 'Логин', 'Автор', 'Произведений'], $rows);
        }

        $io->writeln(sprintf(
            'Дней обработано: %d из %d',
            $result['datesDone'],
            $result['datesTotal'],
        ));
        $io->writeln(sprintf(
            'Страниц потока обработано: %d (ошибок: %d)',
            $result['streamPages'],
            $result['streamFailed'],
        ));
        $io->writeln(sprintf(
            'Авторов в потоке (сумма уникальных по каждому дню): %d',
            $result['authorsSeen'],
        ));
        $io->writeln(sprintf(
            'Проверено страниц авторов: %d (ошибок: %d)',
            $result['authorsChecked'],
            $result['failedChecks'],
        ));
        $io->writeln(sprintf(
            'Новых монстров: %d',
            count($result['newMonsters']),
        ));

        $pending = $result['pendingDates'];
        if ($pending !== []) {
            $io->warning(sprintf(
                'Осталось незавершённых дней: %d (%s%s). Запустите команду повторно — она продолжит с них.',
                count($pending),
                implode(', ', array_slice($pending, 0, 10)),
                count($pending) > 10 ? ', …' : '',
            ));
        }

        if ($result['stopped']) {
            $io->note('Работа прервана по сигналу, накопленные данные сохранены.');

            return Command::SUCCESS;
        }

        $io->success(
            $dryRun
                ? 'План найден, изменения в БД не вносились'
                : sprintf('Найдено и добавлено монстров: %d', count($result['newMonsters'])),
        );

        return Command::SUCCESS;
    }

    /**
     * @return list<int>
     */
    public function getSubscribedSignals(): array
    {
        if (!\defined('SIGINT') || !\defined('SIGTERM')) {
            return [];
        }

        return [\SIGINT, \SIGTERM];
    }

    /**
     * First interrupt asks the service to stop between requests so the current
     * state is committed; a second one aborts immediately.
     */
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        if (++$this->signals > 1) {
            return Command::FAILURE;
        }

        $this->discoveryService->requestStop();

        return false;
    }

    private function resolveFrom(mixed $value, SymfonyStyle $io): ?\DateTimeImmutable
    {
        if ($value === null) {
            return new \DateTimeImmutable((new \DateTimeImmutable('now'))->format('Y-01-01'));
        }

        $date = $this->parseDate((string) $value, $io);
        if ($date === null) {
            return null;
        }

        return $date->setTime(0, 0);
    }

    private function resolveTo(mixed $value, SymfonyStyle $io): ?\DateTimeImmutable
    {
        if ($value === null) {
            return new \DateTimeImmutable('today');
        }

        return $this->parseDate((string) $value, $io);
    }

    private function parseDate(string $value, SymfonyStyle $io): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false) {
            $io->error(sprintf('Некорректная дата "%s" (ожидается YYYY-MM-DD)', $value));

            return null;
        }

        return $date;
    }
}
