<?php

namespace App\Command;

use App\Entity\BlogComment;
use App\Entity\BlogPost;
use App\Entity\StaticText;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate:legacy:blog',
    description: 'Миграция данных блога blog.poethrenoff.ru из JSON файлов'
)]
class MigrateLegacyBlogCommand extends AbstractMigrateLegacyCommand
{
    private EntityManagerInterface $entityManager;
    private string $projectDir;

    /**
     * Маппинг старого tag_id -> реальный сохранённый Tag.
     * Нужен, т.к. в legacy-данных встречаются теги с одинаковым title
     * (разные tag_id), а поле title уникально.
     *
     * @var array<int, Tag>
     */
    private array $tagIdMap = [];

    /**
     * Кеш загруженных постов (old news_id -> BlogPost) для связывания тегов.
     *
     * @var array<int, BlogPost>
     */
    private array $postIdMap = [];

    public function __construct(EntityManagerInterface $entityManager, string $projectDir)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->projectDir = $projectDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $migrationDir = $this->projectDir . '/var/migration/blog';

        if (!is_dir($migrationDir)) {
            $io->error("Директория миграции не найдена: $migrationDir");
            return Command::FAILURE;
        }

        $io->title('Миграция данных блога');

        try {
            // 1. Миграция BlogPost
            $io->section('Миграция постов (BlogPost)');
            $this->migratePosts($migrationDir, $io);

            // 2. Миграция Tag
            $io->section('Миграция тегов (Tag)');
            $this->migrateTags($migrationDir, $io);

            // 3. Миграция связи Tag <-> BlogPost
            $io->section('Миграция связей тегов с постами');
            $this->migrateNewsTags($migrationDir, $io);

            // 4. Миграция BlogComment
            $io->section('Миграция комментариев (BlogComment)');
            $this->migrateComments($migrationDir, $io);

            // 5. Миграция StaticText (объединение с сайтом)
            $io->section('Миграция статических текстов блога');
            $this->migrateStaticTexts($migrationDir, $io);

            $io->success('Миграция блога успешно завершена!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Ошибка миграции: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function migratePosts(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/news.json';
        $data = $this->loadRecords($file, $io);
        $count = 0;

        foreach ($data as $row) {
            $post = new BlogPost();
            $post->setId((int)$row['news_id']);
            $post->setContent($row['news_content']);
            $post->setIsActive((bool)$row['news_active']);

            // Парсинг даты формата YYYYMMDDHHMMSS
            $date = \DateTimeImmutable::createFromFormat('YmdHis', $row['news_date']);
            if ($date) {
                $post->setPublishedAt($date);
            } else {
                $io->warning("Не удалось распарсить дату для news_id={$row['news_id']}: {$row['news_date']}");
                $post->setPublishedAt(new \DateTimeImmutable());
            }

            $this->entityManager->persist($post);
            $count++;

            if ($count % 100 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $io->writeln("Мигрировано $count записей...");
            }
        }

        $this->entityManager->flush();
        $io->writeln("Всего мигрировано постов: $count");
    }

    private function migrateTags(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/tag.json';
        $data = $this->loadRecords($file, $io);
        $count = 0;
        $skipped = 0;

        // title -> Tag: дубликаты (разные tag_id, одинаковый текст) подменяем
        // уже сохранённым тегом, т.к. поле title уникально.
        $byTitle = [];

        foreach ($data as $row) {
            $oldId = (int)$row['tag_id'];
            $title = $row['tag_title'];

            if (isset($byTitle[$title])) {
                // Запоминаем маппинг старого id на реальный (уже сохранённый) тег.
                $this->tagIdMap[$oldId] = $byTitle[$title];
                $skipped++;
                continue;
            }

            $tag = new Tag();
            $tag->setId($oldId);
            $tag->setTitle($title);

            $this->entityManager->persist($tag);
            $byTitle[$title] = $tag;
            $this->tagIdMap[$oldId] = $tag;
            $count++;
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
        $io->writeln("Всего мигрировано тегов: $count (пропущено дублей: $skipped)");
    }

    private function migrateNewsTags(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/news_tag.json';
        $data = $this->loadRecords($file, $io);
        $count = 0;
        $missing = 0;

        $this->rebuildTagMap();
        $this->rebuildPostMap();

        foreach ($data as $row) {
            $oldPostId = (int)$row['news_id'];
            $oldTagId = (int)$row['tag_id'];

            if (!isset($this->postIdMap[$oldPostId]) || !isset($this->tagIdMap[$oldTagId])) {
                $missing++;
                continue;
            }

            $this->postIdMap[$oldPostId]->addTag($this->tagIdMap[$oldTagId]);
            $count++;

            if ($count % 100 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                // После clear() сущности detached — перестраиваем карты.
                $this->rebuildTagMap();
                $this->rebuildPostMap();
                $io->writeln("Мигрировано $count связей...");
            }
        }

        $this->entityManager->flush();
        $io->writeln("Всего мигрировано связей: $count (пропущено без тега/поста: $missing)");
    }

    private function rebuildTagMap(): void
    {
        $this->tagIdMap = [];
        foreach ($this->entityManager->getRepository(Tag::class)->findAll() as $tag) {
            $this->tagIdMap[$tag->getId()] = $tag;
        }
    }

    private function rebuildPostMap(): void
    {
        $this->postIdMap = [];
        foreach ($this->entityManager->getRepository(BlogPost::class)->findAll() as $post) {
            $this->postIdMap[$post->getId()] = $post;
        }
    }

    private function migrateComments(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/comment.json';
        $data = $this->loadRecords($file, $io);
        $count = 0;

        // Этап 1: сохраняем все комментарии без родителя (сохраняя оригинальные ID).
        $pendingParents = [];
        foreach ($data as $row) {
            $comment = new BlogComment();
            $comment->setId((int)$row['comment_id']);
            $comment->setAuthor($row['comment_author']);
            $comment->setContent($row['comment_content']);
            $comment->setInfo($row['comment_info'] ?: null);
            $comment->setIsActive(true);

            $post = $this->entityManager->getReference(BlogPost::class, (int)$row['comment_news']);
            $comment->setPost($post);

            $parentId = (int)$row['comment_parent'];
            if ($parentId > 0) {
                $pendingParents[(int)$row['comment_id']] = $parentId;
            }

            // Парсинг даты формата YYYYMMDDHHMMSS
            $date = \DateTimeImmutable::createFromFormat('YmdHis', $row['comment_date']);
            if ($date) {
                $comment->setCreatedAt($date);
            } else {
                $io->warning("Не удалось распарсить дату для comment_id={$row['comment_id']}: {$row['comment_date']}");
                $comment->setCreatedAt(new \DateTimeImmutable());
            }

            $this->entityManager->persist($comment);
            $count++;

            if ($count % 100 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $io->writeln("Мигрировано $count записей...");
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        // Этап 2: проставляем родителя по уже сохранённым комментариям.
        if ($pendingParents !== []) {
            $comments = $this->entityManager->getRepository(BlogComment::class)->findAll();
            $byId = [];
            foreach ($comments as $c) {
                $byId[$c->getId()] = $c;
            }
            foreach ($pendingParents as $childId => $parentId) {
                if (!isset($byId[$childId], $byId[$parentId])) {
                    $io->warning("Пропущена связь комментария: child=$childId, parent=$parentId");
                    continue;
                }
                $byId[$childId]->setParent($byId[$parentId]);
            }
            $this->entityManager->flush();
        }

        $io->writeln("Всего мигрировано комментариев: $count");
    }

    private function migrateStaticTexts(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/text.json';
        $data = $this->loadRecords($file, $io);
        $count = 0;

        foreach ($data as $row) {
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
}
