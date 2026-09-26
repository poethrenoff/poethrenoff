<?php

namespace App\Command;

use App\Entity\BlogComment;
use App\Entity\BlogPost;
use App\Entity\Poem;
use App\Entity\PublicationLog;
use App\Entity\Tag;
use App\Entity\Work;
use App\Entity\WorkGroup;
use App\Enum\PoemStatus;
use App\Service\WorkService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Экспорт всего корпуса (сайт + Мастерская + лог публикаций + блог) в JSONL.
 *
 * Только чтение БД через сущности сайта. Один источник данных для всех
 * ИИ-чатов проекта «100000» вместо txt-архива и ручных выгрузок.
 */
#[AsCommand(
    name: 'app:export:corpus',
    description: 'Экспортирует корпус (Work, WorkGroup, Poem, PublicationLog, блог) в JSONL + manifest.json',
)]
class ExportCorpusCommand extends Command
{
    private const string DEFAULT_OUTPUT = 'var/export/corpus';

    /** Через сколько сущностей очищать UnitOfWork при потоковом чтении. */
    private const int BATCH_SIZE = 500;

    private const int JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkService $workService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Каталог для файлов экспорта (по умолчанию var/export/corpus)',
                self::DEFAULT_OUTPUT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $outputDir = rtrim((string) $input->getOption('output'), '/');

        if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            $io->error(sprintf('Не удалось создать каталог %s', $outputDir));

            return Command::FAILURE;
        }

        $startedAt = new \DateTimeImmutable();

        $groups = $this->exportGroups($outputDir);
        $works = $this->exportWorks($outputDir);
        $poems = $this->exportPoems($outputDir);
        $publications = $this->exportPublications($outputDir);
        $blogPosts = $this->exportBlogPosts($outputDir);
        $blogComments = $this->exportBlogComments($outputDir);

        $manifest = [
            'exported_at' => $startedAt->format(\DateTimeInterface::ATOM),
            'database' => $this->describeDatabase(),
            'files' => [
                'groups.jsonl' => $groups['count'],
                'works.jsonl' => $works['count'],
                'poems.jsonl' => $poems['count'],
                'publications.jsonl' => $publications['count'],
                'blog_posts.jsonl' => $blogPosts['count'],
                'blog_comments.jsonl' => $blogComments['count'],
            ],
            'works' => $works['stats'],
            'poems' => $poems['stats'],
            'publications' => $publications['stats'],
            'blog' => [
                'posts' => $blogPosts['stats'],
                'comments' => $blogComments['stats'],
            ],
        ];

        $this->writeJson($outputDir . '/manifest.json', $manifest);

        $io->success(sprintf(
            'Экспорт в %s: разделов %d, произведений %d, черновиков %d, публикаций %d, '
            . 'записей блога %d, комментариев блога %d',
            $outputDir,
            $groups['count'],
            $works['count'],
            $poems['count'],
            $publications['count'],
            $blogPosts['count'],
            $blogComments['count'],
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array{count: int}
     */
    private function exportGroups(string $outputDir): array
    {
        $handle = $this->openFile($outputDir . '/groups.jsonl');
        $count = 0;

        foreach ($this->iterate(WorkGroup::class, ['e.parent' => 'p']) as $group) {
            $this->writeLine($handle, [
                'id' => $group->getId(),
                'parent_id' => $group->getParent()?->getId(),
                'title' => $group->getTitle(),
                'period' => $group->getComment(),
                'position' => $group->getPosition(),
                'is_favorite' => $group->getIsFavorite(),
                'is_active' => $group->getIsActive(),
            ]);
            $count++;
        }

        fclose($handle);

        return ['count' => $count];
    }

    /**
     * @return array{count: int, stats: array<string, mixed>}
     */
    private function exportWorks(string $outputDir): array
    {
        $handle = $this->openFile($outputDir . '/works.jsonl');
        $stats = $this->newStats();
        $count = 0;

        foreach ($this->iterate(Work::class, ['e.group' => 'g']) as $work) {
            $dateRaw = $work->getComment();
            $date = $this->parseDate($dateRaw);
            $group = $work->getGroup();

            $this->writeLine($handle, [
                'id' => $work->getId(),
                'group_id' => $group?->getId(),
                'group_title' => $group?->getTitle(),
                'title' => $work->getTitle(),
                'text' => $work->getText(),
                'date' => $date?->format('Y-m-d'),
                'date_raw' => $dateRaw,
                'position' => $work->getPosition(),
                'is_active' => $work->getIsActive(),
                'is_favorite' => $group?->getIsFavorite() ?? false,
            ]);

            $this->addToStats($stats, $work->getIsActive(), $date);
            $count++;
        }

        fclose($handle);

        return ['count' => $count, 'stats' => $this->finishStats($stats)];
    }

    /**
     * @return array{count: int, stats: array<string, mixed>}
     */
    private function exportPoems(string $outputDir): array
    {
        $handle = $this->openFile($outputDir . '/poems.jsonl');
        $stats = $this->newStats();
        $byStatus = [];
        $count = 0;

        foreach ($this->iterate(Poem::class) as $poem) {
            $status = $poem->getStatus();
            $date = $poem->getComment();

            $this->writeLine($handle, [
                'id' => $poem->getId(),
                'title' => $poem->getTitle(),
                'text' => rtrim($poem->getContent()),
                'date' => $date?->format('Y-m-d'),
                'status' => $status->value,
                'position' => $poem->getPosition(),
                'created_at' => $poem->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updated_at' => $poem->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                'deleted_at' => $poem->getDeletedAt()?->format(\DateTimeInterface::ATOM),
            ]);

            $byStatus[$status->value] = ($byStatus[$status->value] ?? 0) + 1;
            $this->addToStats($stats, $status === PoemStatus::Draft, $date);
            $count++;
        }

        fclose($handle);

        $statsArray = $this->finishStats($stats);
        $statsArray['by_status'] = $byStatus;

        return ['count' => $count, 'stats' => $statsArray];
    }

    /**
     * @return array{count: int, stats: array<string, mixed>}
     */
    private function exportPublications(string $outputDir): array
    {
        $handle = $this->openFile($outputDir . '/publications.jsonl');
        $byPlatform = [];
        $count = 0;

        foreach ($this->iterate(PublicationLog::class, ['e.poem' => 'p']) as $log) {
            $platform = $log->getPlatform()->value;
            $status = $log->getStatus()->value;

            $this->writeLine($handle, [
                'id' => $log->getId(),
                'poem_id' => $log->getPoem()->getId(),
                'platform' => $platform,
                'status' => $status,
                'external_post_id' => $log->getExternalPostId(),
                'external_url' => $log->getExternalUrl(),
                'published_at' => $log->getPublishedAt()->format(\DateTimeInterface::ATOM),
            ]);

            $key = $platform . ':' . $status;
            $byPlatform[$key] = ($byPlatform[$key] ?? 0) + 1;
            $count++;
        }

        fclose($handle);

        return ['count' => $count, 'stats' => ['by_platform_status' => $byPlatform]];
    }

    /**
     * @return array{count: int, stats: array<string, mixed>}
     */
    private function exportBlogPosts(string $outputDir): array
    {
        $handle = $this->openFile($outputDir . '/blog_posts.jsonl');
        $count = 0;
        $active = 0;
        $tagsTotal = 0;
        $dateMin = null;
        $dateMax = null;

        foreach ($this->iterate(BlogPost::class) as $post) {
            $isActive = $post->getIsActive();
            $publishedAt = $post->getPublishedAt()->format(\DateTimeInterface::ATOM);
            $tags = array_values(array_map(
                static fn (Tag $tag): string => $tag->getTitle(),
                $post->getTags()->toArray(),
            ));
            sort($tags);

            $this->writeLine($handle, [
                'id' => $post->getId(),
                'content' => rtrim($post->getContent()),
                'published_at' => $publishedAt,
                'is_active' => $isActive,
                'tags' => $tags,
            ]);

            $count++;
            $tagsTotal += count($tags);
            if ($isActive) {
                $active++;
                $dateMin = $dateMin === null || $publishedAt < $dateMin ? $publishedAt : $dateMin;
                $dateMax = $dateMax === null || $publishedAt > $dateMax ? $publishedAt : $dateMax;
            }
        }

        fclose($handle);

        return ['count' => $count, 'stats' => [
            'total' => $count,
            'active' => $active,
            'tag_links' => $tagsTotal,
            'date_min' => $dateMin,
            'date_max' => $dateMax,
        ]];
    }

    /**
     * @return array{count: int, stats: array<string, mixed>}
     */
    private function exportBlogComments(string $outputDir): array
    {
        $handle = $this->openFile($outputDir . '/blog_comments.jsonl');
        $count = 0;
        $active = 0;

        foreach ($this->iterate(BlogComment::class, ['e.post' => 'p', 'e.parent' => 'r']) as $comment) {
            $isActive = $comment->getIsActive();

            $this->writeLine($handle, [
                'id' => $comment->getId(),
                'post_id' => $comment->getPost()?->getId(),
                'parent_id' => $comment->getParent()?->getId(),
                'author' => $comment->getAuthor(),
                'content' => $comment->getContent(),
                'created_at' => $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'is_active' => $isActive,
            ]);

            $count++;
            if ($isActive) {
                $active++;
            }
        }

        fclose($handle);

        return ['count' => $count, 'stats' => ['total' => $count, 'active' => $active]];
    }

    /**
     * Потоковое чтение сущностей по возрастанию id с периодической очисткой
     * UnitOfWork, чтобы 9 000 произведений не висели в памяти разом.
     *
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, string> $joins to-one связи для fetch-join: 'e.group' => 'g'
     * @return iterable<T>
     */
    private function iterate(string $class, array $joins = []): iterable
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from($class, 'e')
            ->orderBy('e.id', 'ASC');

        foreach ($joins as $relation => $alias) {
            $qb->leftJoin($relation, $alias)->addSelect($alias);
        }

        $processed = 0;
        foreach ($qb->getQuery()->toIterable() as $entity) {
            /** @var T $entity */
            yield $entity;

            if (++$processed % self::BATCH_SIZE === 0) {
                $this->entityManager->clear();
            }
        }

        $this->entityManager->clear();
    }

    /**
     * Счётчики для manifest.json по одному источнику (произведения или черновики).
     * «Активные» — активные произведения сайта или черновики не из корзины.
     * Записи без распознанной даты в дни и диапазон не попадают.
     *
     * @return array{total: int, active: int, active_days: array<string, true>,
     *     date_min: string|null, date_max: string|null}
     */
    private function newStats(): array
    {
        return [
            'total' => 0,
            'active' => 0,
            'active_days' => [],
            'date_min' => null,
            'date_max' => null,
        ];
    }

    /**
     * @param array{total: int, active: int, active_days: array<string, true>,
     *     date_min: string|null, date_max: string|null} $stats
     */
    private function addToStats(array &$stats, bool $isActive, ?\DateTimeImmutable $date): void
    {
        $stats['total']++;

        if (!$isActive) {
            return;
        }

        $stats['active']++;

        if ($date === null) {
            return;
        }

        $day = $date->format('Y-m-d');
        $stats['active_days'][$day] = true;
        if ($stats['date_min'] === null || $day < $stats['date_min']) {
            $stats['date_min'] = $day;
        }
        if ($stats['date_max'] === null || $day > $stats['date_max']) {
            $stats['date_max'] = $day;
        }
    }

    /**
     * @param array{total: int, active: int, active_days: array<string, true>,
     *     date_min: string|null, date_max: string|null} $stats
     * @return array<string, mixed>
     */
    private function finishStats(array $stats): array
    {
        $stats['active_days'] = count($stats['active_days']);

        return $stats;
    }

    private function parseDate(?string $raw): ?\DateTimeImmutable
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        return $this->workService->parseCommentDate($trimmed);
    }

    /**
     * @return resource
     */
    private function openFile(string $path)
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Не удалось открыть файл %s для записи', $path));
        }

        return $handle;
    }

    /**
     * @param resource $handle
     * @param array<string, mixed> $record
     */
    private function writeLine($handle, array $record): void
    {
        fwrite($handle, json_encode($record, self::JSON_FLAGS) . "\n");
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, self::JSON_FLAGS | JSON_PRETTY_PRINT) . "\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function describeDatabase(): array
    {
        $connection = $this->entityManager->getConnection();
        $params = $connection->getParams();

        return [
            'driver' => $params['driver'] ?? null,
            'host' => $params['host'] ?? null,
            'port' => $params['port'] ?? null,
            'dbname' => $params['dbname'] ?? null,
            'server_version' => $connection->getServerVersion(),
        ];
    }
}
