<?php

namespace App\Command;

use App\Repository\WorkGroupRepository;
use App\Repository\WorkRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sitemap:generate',
    description: 'Генерирует sitemap.xml для основного сайта',
)]
class GenerateSitemapCommand extends Command
{
    public function __construct(
        private WorkGroupRepository $workGroupRepository,
        private WorkRepository $workRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'output',
                'o',
                \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
                'Путь к выходному файлу (по умолчанию htdocs/www/sitemap.xml)',
                'htdocs/www/sitemap.xml'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $outputPath = $input->getOption('output');

        $groups = $this->workGroupRepository->findAllActiveSorted();
        $works = $this->workRepository->findAllActiveForSitemap();

        $xml = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>'
        );

        $this->addUrl($xml, 'https://poethrenoff.ru/', null, 'daily', '1.0');
        $this->addUrl($xml, 'https://poethrenoff.ru/work', null, 'daily', '0.8');
        $this->addUrl($xml, 'https://poethrenoff.ru/about', null, 'monthly', '0.6');

        foreach ($groups as $group) {
            $this->addUrl(
                $xml,
                'https://poethrenoff.ru/work/group/' . $group->getId(),
                null,
                'weekly',
                '0.7'
            );
        }

        foreach ($works as $row) {
            $this->addUrl(
                $xml,
                'https://poethrenoff.ru/work/view/' . $row['id'],
                null,
                'weekly',
                '0.8'
            );
        }

        $content = $xml->asXML();
        if (!is_string($content)) {
            $io->error('Не удалось сгенерировать XML');

            return Command::FAILURE;
        }

        $dir = dirname($outputPath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            $io->error(sprintf('Не удалось создать директорию %s', $dir));

            return Command::FAILURE;
        }

        file_put_contents($outputPath, $content);
        $io->success(sprintf('Sitemap сохранён в %s (%d URL)', $outputPath, count($groups) + count($works) + 3));

        return Command::SUCCESS;
    }

    private function addUrl(
        \SimpleXMLElement $urlset,
        string $loc,
        ?string $lastmod,
        string $changefreq,
        string $priority,
    ): void {
        $url = $urlset->addChild('url');
        $url->addChild('loc', htmlspecialchars($loc));

        if ($lastmod !== null) {
            $date = new \DateTime($lastmod);
            $url->addChild('lastmod', $date->format('Y-m-d'));
        }

        $url->addChild('changefreq', $changefreq);
        $url->addChild('priority', $priority);
    }
}
