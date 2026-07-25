<?php

namespace App\Command;

use App\Entity\BlogComment;
use App\Entity\BlogPost;
use App\Entity\Monster;
use App\Entity\Picture;
use App\Entity\StaticText;
use App\Entity\Tag;
use App\Entity\Vozdukh;
use App\Entity\Work;
use App\Entity\WorkGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate:legacy',
    description: 'Миграция всех legacy-данных (сайт + блог) из PHP-array файлов'
)]
class MigrateLegacyCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private string $projectDir;

    /**
     * Маппинг старого tag_id -> реальный сохранённый Tag.
     *
     * @var array<int, int>
     */
    private array $tagIdMap = [];

    /**
     * Кеш загруженных постов (old news_id -> BlogPost id) для связывания тегов.
     *
     * @var array<int, int>
     */
    private array $postIdMap = [];

    public function __construct(
        EntityManagerInterface $entityManager,
        #[Autowire(param: 'kernel.project_dir')] string $projectDir
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->projectDir = $projectDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '1G');

        $io = new SymfonyStyle($input, $output);
        $siteDir = $this->projectDir . '/var/migration/site';
        $blogDir = $this->projectDir . '/var/migration/blog';

        if (!is_dir($siteDir)) {
            $io->error("Директория миграции сайта не найдена: $siteDir");
            return Command::FAILURE;
        }

        if (!is_dir($blogDir)) {
            $io->error("Директория миграции блога не найдена: $blogDir");
            return Command::FAILURE;
        }

        $io->title('Миграция всех legacy-данных');

        try {
            $this->truncateTable($this->entityManager, StaticText::class, $io);
            $this->truncateTable($this->entityManager, Picture::class, $io);
            $this->truncateTable($this->entityManager, Work::class, $io);
            $this->truncateTable($this->entityManager, WorkGroup::class, $io);
            $this->truncateTable($this->entityManager, BlogComment::class, $io);
            $this->entityManager->getConnection()->executeStatement('DELETE FROM tag_blog_post');
            $io->writeln('Очищена таблица: tag_blog_post');
            $this->truncateTable($this->entityManager, BlogPost::class, $io);
            $this->truncateTable($this->entityManager, Tag::class, $io);
            $this->truncateTable($this->entityManager, Monster::class, $io);
            $this->truncateTable($this->entityManager, Vozdukh::class, $io);

            // 1. Миграция разделов сайта (Group)
            $io->section('Миграция разделов сайта (WorkGroup)');
            $this->migrateGroups($siteDir, $io);

            // 2. Миграция произведений сайта (Work)
            $io->section('Миграция произведений сайта (Work)');
            $this->migrateWorks($siteDir, $io);

            // 3. Миграция изображений сайта (Picture)
            $io->section('Миграция изображений сайта (Picture)');
            $this->migratePictures($siteDir, $io);

            // 4. Миграция постов блога (BlogPost)
            $io->section('Миграция постов блога (BlogPost)');
            $this->migratePosts($blogDir, $io);

            // 5. Миграция тегов блога (Tag)
            $io->section('Миграция тегов блога (Tag)');
            $this->migrateTags($blogDir, $io);

            // 6. Миграция связей тегов с постами
            $io->section('Миграция связей тегов с постами');
            $this->migrateNewsTags($blogDir, $io);

            // 7. Миграция комментариев блога (BlogComment)
            $io->section('Миграция комментариев блога (BlogComment)');
            $this->migrateComments($blogDir, $io);

            // 8. Миграция статических текстов (объединение сайта и блога)
            $io->section('Миграция статических текстов');
            $this->migrateStaticTexts($siteDir, $blogDir, $io);

            // 9. Миграция monster
            $io->section('Миграция monster');
            $this->migrateMonsters($siteDir, $io);

            // 10. Миграция vozdukh
            $io->section('Миграция vozdukh');
            $this->migrateVozdukh($siteDir, $io);

            $io->success('Миграция всех данных успешно завершена!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Ошибка миграции: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function migrateGroups(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/work_group.php';
        $data = $this->loadRecords($file, $io);
        $count = 0;
        $pendingParents = [];

        foreach ($data as $row) {
            $this->rawInsert($this->entityManager, 'work_group', [
                'id' => (int)$row['group_id'],
                'title' => $row['group_title'],
                'comment' => $row['group_comment'] ?: null,
                'position' => (float)$row['group_order'],
                'is_favorite' => (int)($row['group_parent'] == 66),
                'is_active' => (int)(bool)$row['group_active'],
                'parent_id' => null,
            ]);
            $count++;

            $parentId = (int)$row['group_parent'];
            if ($parentId > 0) {
                $pendingParents[(int)$row['group_id']] = $parentId;
            }

            if ($count % 100 === 0) {
                $io->writeln("Мигрировано $count записей...");
            }
        }

        foreach ($pendingParents as $childId => $parentId) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE work_group SET parent_id = :parent WHERE id = :child',
                ['parent' => $parentId, 'child' => $childId]
            );
        }

        $io->writeln("Всего мигрировано групп: $count");
    }

    private function migrateWorks(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/work.php';
        $data = $this->loadRecords($file, $io);
        $count = 0;

        foreach ($data as $row) {
            $this->rawInsert($this->entityManager, 'work', [
                'id' => (int)$row['work_id'],
                'title' => $row['work_title'],
                'text' => $row['work_text'] ?? '',
                'comment' => $row['work_comment'] ?: null,
                'position' => (float)$row['work_order'],
                'is_active' => (int)(bool)$row['work_active'],
                'group_id' => (int)$row['work_group'],
                'likes_count' => 0,
                'dislikes_count' => 0,
            ]);
            $count++;

            if ($count % 100 === 0) {
                $io->writeln("Мигрировано $count записей...");
            }
        }

        $io->writeln("Всего мигрировано произведений: $count");
    }

    private function migratePictures(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/picture.php';
        $data = $this->loadRecords($file, $io);
        $count = 0;

        foreach ($data as $row) {
            $date = \DateTimeImmutable::createFromFormat('YmdHis', $row['picture_date']);
            if ($date) {
                $dateStr = $date->format('Y-m-d');
            } else {
                $io->warning("Не удалось распарсить дату для picture_id={$row['picture_id']}: {$row['picture_date']}");
                $dateStr = (new \DateTimeImmutable())->format('Y-m-d');
            }

            $this->rawInsert($this->entityManager, 'picture', [
                'id' => (int)$row['picture_id'],
                'title' => $row['picture_title'],
                'image_path' => $row['picture_image'],
                'source_path' => $row['picture_source'] ?: null,
                'date' => $dateStr,
                'position' => (float)$row['picture_order'],
                'is_active' => (int)(bool)$row['picture_active'],
            ]);
            $count++;

            if ($count % 100 === 0) {
                $io->writeln("Мигрировано $count записей...");
            }
        }

        $io->writeln("Всего мигрировано изображений: $count");
    }

    private function migratePosts(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/news.php';
        $data = $this->loadRecords($file, $io);
        $count = 0;

        foreach ($data as $row) {
            $date = \DateTimeImmutable::createFromFormat('YmdHis', $row['news_date']);
            if ($date) {
                $dateStr = $date->format('Y-m-d H:i:s');
            } else {
                $io->warning("Не удалось распарсить дату для news_id={$row['news_id']}: {$row['news_date']}");
                $dateStr = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            }

            $this->rawInsert($this->entityManager, 'blog_post', [
                'id' => (int)$row['news_id'],
                'content' => $row['news_content'],
                'published_at' => $dateStr,
                'is_active' => (int)(bool)$row['news_active'],
            ]);
            $count++;

            if ($count % 100 === 0) {
                $io->writeln("Мигрировано $count записей...");
            }
        }

        $this->postIdMap = [];
        $rows = $this->entityManager->getConnection()->fetchAllAssociative('SELECT id FROM blog_post');
        foreach ($rows as $row) {
            $this->postIdMap[$row['id']] = $row['id'];
        }

        $io->writeln("Всего мигрировано постов: $count");
    }

    private function migrateTags(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/tag.php';
        $data = $this->loadRecords($file, $io);
        $count = 0;
        $skipped = 0;
        $byTitle = [];

        foreach ($data as $row) {
            $oldId = (int)$row['tag_id'];
            $title = $row['tag_title'];

            if (isset($byTitle[$title])) {
                $this->tagIdMap[$oldId] = $byTitle[$title];
                $skipped++;
                continue;
            }

            $this->rawInsert($this->entityManager, 'tag', [
                'id' => $oldId,
                'title' => $title,
            ]);
            $byTitle[$title] = $oldId;
            $this->tagIdMap[$oldId] = $oldId;
            $count++;
        }

        $io->writeln("Всего мигрировано тегов: $count (пропущено дублей: $skipped)");
    }

    private function migrateNewsTags(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/news_tag.php';
        $data = $this->loadRecords($file, $io);
        $count = 0;
        $missing = 0;

        foreach ($data as $row) {
            $oldPostId = (int)$row['news_id'];
            $oldTagId = (int)$row['tag_id'];

            if (!isset($this->postIdMap[$oldPostId]) || !isset($this->tagIdMap[$oldTagId])) {
                $missing++;
                continue;
            }

            $this->rawInsert($this->entityManager, 'tag_blog_post', [
                'blog_post_id' => $this->postIdMap[$oldPostId],
                'tag_id' => $this->tagIdMap[$oldTagId],
            ]);
            $count++;

            if ($count % 100 === 0) {
                $io->writeln("Мигрировано $count связей...");
            }
        }

        $io->writeln("Всего мигрировано связей: $count (пропущено без тега/поста: $missing)");
    }

    private function migrateComments(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/comment.php';
        $data = $this->loadRecords($file, $io);
        $count = 0;
        $pendingParents = [];

        foreach ($data as $row) {
            $date = \DateTimeImmutable::createFromFormat('YmdHis', $row['comment_date']);
            if ($date) {
                $dateStr = $date->format('Y-m-d H:i:s');
            } else {
                $io->warning("Не удалось распарсить дату для comment_id={$row['comment_id']}: {$row['comment_date']}");
                $dateStr = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            }

            $this->rawInsert($this->entityManager, 'blog_comment', [
                'id' => (int)$row['comment_id'],
                'author' => $row['comment_author'],
                'content' => $row['comment_content'],
                'info' => $row['comment_info'] ?: null,
                'created_at' => $dateStr,
                'is_active' => 1,
                'post_id' => (int)$row['comment_news'],
                'parent_id' => null,
            ]);
            $count++;

            $parentId = (int)$row['comment_parent'];
            if ($parentId > 0) {
                $pendingParents[(int)$row['comment_id']] = $parentId;
            }

            if ($count % 100 === 0) {
                $io->writeln("Мигрировано $count записей...");
            }
        }

        foreach ($pendingParents as $childId => $parentId) {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE blog_comment SET parent_id = :parent WHERE id = :child',
                ['parent' => $parentId, 'child' => $childId]
            );
        }

        $io->writeln("Всего мигрировано комментариев: $count");
    }

    private function migrateStaticTexts(string $siteDir, string $blogDir, SymfonyStyle $io): void
    {
        $count = 0;

        $siteFile = $siteDir . '/text.php';
        $siteData = $this->loadRecords($siteFile, $io);
        foreach ($siteData as $row) {
            $text = new StaticText();
            $text->setSlug($row['text_tag']);
            $text->setTitle($row['text_title']);
            $text->setContent($row['text_content'] ?? '');

            $this->entityManager->persist($text);
            $count++;
        }

        $blogFile = $blogDir . '/text.php';
        $blogData = $this->loadRecords($blogFile, $io);
        foreach ($blogData as $row) {
            $text = new StaticText();
            $text->setSlug($row['text_tag']);
            $text->setTitle($row['text_title']);
            $text->setContent($row['text_content'] ?? '');

            $this->entityManager->persist($text);
            $count++;
        }

        $this->entityManager->flush();
        $io->writeln("Всего мигрировано текстов: $count");
    }

    private function migrateMonsters(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/monster.php';
        $data = $this->loadRecords($file, $io);
        $count = 0;

        foreach ($data as $row) {
            $date = \DateTimeImmutable::createFromFormat('YmdHis', $row['monster_date']);
            if ($date) {
                $dateStr = $date->format('Y-m-d H:i:s');
            } else {
                $io->warning("Не удалось распарсить дату для monster_id={$row['monster_id']}: {$row['monster_date']}");
                $dateStr = null;
            }

            $this->rawInsert($this->entityManager, 'monster', [
                'id' => (int)$row['monster_id'],
                'login' => $row['monster_login'],
                'author' => $row['monster_title'],
                'poems' => (int)$row['monster_count'],
                'poems_old' => (int)$row['monster_count_old'],
                'place' => $row['monster_place'] !== '' ? (int)$row['monster_place'] : null,
                'place_old' => $row['monster_place_old'] !== '' ? (int)$row['monster_place_old'] : null,
                'last_visit_date' => $dateStr,
                'is_active' => (int)(bool)$row['monster_active'],
            ]);
            $count++;

            if ($count % 100 === 0) {
                $io->writeln("Мигрировано $count записей...");
            }
        }

        $io->writeln("Всего мигрировано monster: $count");
    }

    private function migrateVozdukh(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/vozdukh_work.php';
        $data = $this->loadRecords($file, $io);
        $count = 0;

        foreach ($data as $row) {
            $this->rawInsert($this->entityManager, 'vozdukh', [
                'id' => (int)$row['work_id'],
                'title' => $row['work_title'],
                'subtitle' => $row['work_subtitle'] ?: null,
                'author' => $row['work_author'],
                'text' => $row['work_text'] ?? '',
                'url' => $row['work_url'] ?: null,
                'is_active' => (int)(bool)$row['work_active'],
            ]);
            $count++;

            if ($count % 100 === 0) {
                $io->writeln("Мигрировано $count записей...");
            }
        }

        $io->writeln("Всего мигрировано vozdukh: $count");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadRecords(string $file, SymfonyStyle $io): array
    {
        ini_set('memory_limit', '1G');

        if (!file_exists($file)) {
            $io->warning("Файл не найден: $file");
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false || $content === '') {
            $io->warning("Пустой или нечитаемый файл: $file");
            return [];
        }

        $records = $this->decodeRecords($content);
        if ($records === null || !is_array($records)) {
            $io->warning("Не удалось распарсить PHP-array в файле: $file");
            return [];
        }

        return $records;
    }

    protected function truncateTable(EntityManagerInterface $entityManager, string $entityClass, SymfonyStyle $io): void
    {
        try {
            $entityManager->createQuery('DELETE FROM ' . $entityClass)->execute();
            $shortName = (new \ReflectionClass($entityClass))->getShortName();
            $io->writeln("Очищена таблица: $shortName");
        } catch (\Exception $e) {
            $io->warning("Не удалось очистить таблицу " .
                (new \ReflectionClass($entityClass))->getShortName() . ': ' . $e->getMessage());
        }
    }

    protected function rawInsert(EntityManagerInterface $entityManager, string $table, array $data): void
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);
        $sql = 'INSERT INTO ' . $table .
            ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $entityManager->getConnection()->executeStatement($sql, $data);
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function decodeRecords(string $content): ?array
    {
        $content = ltrim($content);

        $content = preg_replace('/^\s*\/\*.*?\*\/\s*/s', '', $content);
        $content = preg_replace('/^\$\w+\s*=\s*/', '', $content);
        $content = rtrim($content, ";\n\r\t ");

        $records = @eval('return ' . $content . ';');
        if (is_array($records) && isset($records[0]) && is_array($records[0])) {
            return $records;
        }

        return null;
    }
}
