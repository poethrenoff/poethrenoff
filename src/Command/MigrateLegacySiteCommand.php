<?php

namespace App\Command;

use App\Entity\Picture;
use App\Entity\StaticText;
use App\Entity\Work;
use App\Entity\WorkGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate:legacy:site',
    description: 'Миграция данных сайта poethrenoff.ru из JSON файлов'
)]
class MigrateLegacySiteCommand extends AbstractMigrateLegacyCommand
{
    private EntityManagerInterface $entityManager;
    private string $projectDir;

    public function __construct(EntityManagerInterface $entityManager, string $projectDir)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->projectDir = $projectDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $migrationDir = $this->projectDir . '/var/migration/site';

        if (!is_dir($migrationDir)) {
            $io->error("Директория миграции не найдена: $migrationDir");
            return Command::FAILURE;
        }

        $io->title('Миграция данных сайта');

        try {
            // 1. Миграция Group
            $io->section('Миграция разделов (Group)');
            $this->migrateGroups($migrationDir, $io);

            // 2. Миграция Work
            $io->section('Миграция произведений (Work)');
            $this->migrateWorks($migrationDir, $io);

            // 3. Миграция Picture
            $io->section('Миграция изображений (Picture)');
            $this->migratePictures($migrationDir, $io);

            // 4. Миграция StaticText
            $io->section('Миграция статических текстов (StaticText)');
            $this->migrateStaticTexts($migrationDir, $io);

            $io->success('Миграция сайта успешно завершена!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Ошибка миграции: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function migrateGroups(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/work_group.json';
        $data = $this->loadRecords($file, $io);
        $count = 0;

        // Этап 1: сохраняем все группы без родителя (сохраняя оригинальные ID).
        // Двухэтапная вставка исключает конфликт identity-map, когда дочерний
        // элемент встречается в файле раньше родительского.
        $pendingParents = [];
        foreach ($data as $row) {
            $group = new WorkGroup();
            $group->setId((int)$row['group_id']);
            $group->setTitle($row['group_title']);
            $group->setComment($row['group_comment'] ?: null);
            $group->setPosition((float)$row['group_order']);
            $group->setIsActive((bool)$row['group_active']);

            $parentId = (int)$row['group_parent'];
            if ($parentId > 0) {
                $pendingParents[(int)$row['group_id']] = $parentId;
            }

            $this->entityManager->persist($group);
            $count++;

            if ($count % 100 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $io->writeln("Мигрировано $count записей...");
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        // Этап 2: проставляем родителя по уже сохранённым группам.
        if ($pendingParents !== []) {
            $groups = $this->entityManager->getRepository(WorkGroup::class)->findAll();
            $byId = [];
            foreach ($groups as $g) {
                $byId[$g->getId()] = $g;
            }
            foreach ($pendingParents as $childId => $parentId) {
                if (!isset($byId[$childId], $byId[$parentId])) {
                    $io->warning("Пропущена связь групп: child=$childId, parent=$parentId");
                    continue;
                }
                $byId[$childId]->setParent($byId[$parentId]);
            }
            $this->entityManager->flush();
        }

        $io->writeln("Всего мигрировано групп: $count");
    }

    private function migrateWorks(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/work.json';
        $data = $this->loadRecords($file, $io);
        $count = 0;

        foreach ($data as $row) {
            $work = new Work();
            $work->setId((int)$row['work_id']);
            $work->setTitle($row['work_title']);
            $work->setText($row['work_text'] ?? '');
            $work->setComment($row['work_comment'] ?: null);
            $work->setPosition((float)$row['work_order']);
            $work->setIsActive((bool)$row['work_active']);

            $group = $this->entityManager->getReference(WorkGroup::class, (int)$row['work_group']);
            $work->setGroup($group);

            $this->entityManager->persist($work);
            $count++;

            if ($count % 100 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $io->writeln("Мигрировано $count записей...");
            }
        }

        $this->entityManager->flush();
        $io->writeln("Всего мигрировано произведений: $count");
    }

    private function migratePictures(string $migrationDir, SymfonyStyle $io): void
    {
        $file = $migrationDir . '/picture.json';
        $data = $this->loadRecords($file, $io);
        $count = 0;

        foreach ($data as $row) {
            $picture = new Picture();
            $picture->setId((int)$row['picture_id']);
            $picture->setTitle($row['picture_title']);
            $picture->setImagePath($row['picture_image']);
            $picture->setSourcePath($row['picture_source'] ?: null);
            $picture->setPosition((float)$row['picture_order']);
            $picture->setIsActive((bool)$row['picture_active']);

            // Парсинг даты формата YYYYMMDDHHMMSS
            $date = \DateTimeImmutable::createFromFormat('YmdHis', $row['picture_date']);
            if ($date) {
                $picture->setDate($date);
            } else {
                $io->warning("Не удалось распарсить дату для picture_id={$row['picture_id']}: {$row['picture_date']}");
                $picture->setDate(new \DateTimeImmutable());
            }

            $this->entityManager->persist($picture);
            $count++;

            if ($count % 100 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $io->writeln("Мигрировано $count записей...");
            }
        }

        $this->entityManager->flush();
        $io->writeln("Всего мигрировано изображений: $count");
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
