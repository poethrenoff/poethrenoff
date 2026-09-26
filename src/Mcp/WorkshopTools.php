<?php

namespace App\Mcp;

use App\Entity\Poem;
use App\Entity\PoemVersion;
use App\Entity\PublicationLog;
use App\Enum\PoemStatus;
use App\Repository\PoemRepository;
use App\Repository\PublicationLogRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

/**
 * MCP-инструменты Мастерской: черновики и лог публикаций. Только чтение.
 */
class WorkshopTools
{
    private const int MAX_LIMIT = 50;

    public function __construct(
        private PoemRepository $poemRepository,
        private PublicationLogRepository $publicationLogRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'list_drafts',
        description: 'Черновики Мастерской целиком (свежие стихи, ещё не перенесённые на сайт). '
            . 'status: draft — лента, trash — корзина, all — всё. Черновиков немного, отдаются с текстом.',
    )]
    public function listDrafts(
        #[Schema(description: 'draft | trash | all')]
        string $status = 'draft',
    ): array {
        $poems = match ($status) {
            'draft' => $this->poemRepository->findByStatus(PoemStatus::Draft),
            'trash' => $this->poemRepository->findByStatus(PoemStatus::Trash),
            'all' => array_merge(
                $this->poemRepository->findByStatus(PoemStatus::Draft),
                $this->poemRepository->findByStatus(PoemStatus::Trash),
            ),
            default => null,
        };

        if ($poems === null) {
            return ['error' => 'status должен быть draft, trash или all'];
        }

        return [
            'status' => $status,
            'total' => count($poems),
            'items' => array_map(fn (Poem $poem): array => $this->formatPoem($poem), $poems),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_draft',
        description: 'Черновик Мастерской по id с историей правок (версиями).',
    )]
    public function getDraft(
        #[Schema(description: 'Id черновика')]
        int $id,
    ): array {
        $poem = $this->poemRepository->find($id);
        if ($poem === null) {
            return ['error' => sprintf('Черновик %d не найден', $id)];
        }

        $result = $this->formatPoem($poem);
        $result['versions'] = array_map(
            static fn (PoemVersion $version): array => [
                'id' => $version->getId(),
                'title' => $version->getTitle(),
                'date' => $version->getComment()?->format('Y-m-d'),
                'text' => $version->getContent(),
                'created_at' => $version->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            $poem->getVersions()->toArray(),
        );

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'publications',
        description: 'Лог публикаций стихов из Мастерской в соцсети (пока только Telegram), новые первыми. '
            . 'Опционально — только одна платформа: telegram | vk | livejournal.',
    )]
    public function publications(
        #[Schema(description: 'Платформа; пусто — все')]
        ?string $platform = null,
        #[Schema(description: 'Сколько записей, не больше 50')]
        int $limit = 50,
    ): array {
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $logs = $this->publicationLogRepository->findRecent($limit);

        if ($platform !== null) {
            $logs = array_values(array_filter(
                $logs,
                static fn (PublicationLog $log): bool => $log->getPlatform()->value === $platform,
            ));
        }

        return [
            'platform' => $platform,
            'total' => count($logs),
            'items' => array_map(static fn (PublicationLog $log): array => [
                'id' => $log->getId(),
                'draft_id' => $log->getPoem()->getId(),
                'platform' => $log->getPlatform()->value,
                'status' => $log->getStatus()->value,
                'external_url' => $log->getExternalUrl(),
                'published_at' => $log->getPublishedAt()->format(\DateTimeInterface::ATOM),
            ], $logs),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPoem(Poem $poem): array
    {
        return [
            'id' => $poem->getId(),
            'title' => $poem->getDisplayTitle(),
            'date' => $poem->getComment()?->format('Y-m-d'),
            'status' => $poem->getStatus()->value,
            'text' => rtrim($poem->getContent()),
            'created_at' => $poem->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $poem->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
