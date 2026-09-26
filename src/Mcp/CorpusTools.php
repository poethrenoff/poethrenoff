<?php

namespace App\Mcp;

use App\Entity\WorkGroup;
use App\Enum\PoemStatus;
use App\Repository\BlogPostRepository;
use App\Repository\PoemRepository;
use App\Repository\WorkGroupRepository;
use App\Repository\WorkRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

/**
 * MCP-инструменты уровня корпуса: сводка и разделы. Только чтение.
 */
class CorpusTools
{
    public const string SCHEMA_VERSION = '1';

    public function __construct(
        private WorkRepository $workRepository,
        private WorkGroupRepository $workGroupRepository,
        private PoemRepository $poemRepository,
        private BlogPostRepository $blogPostRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'corpus_summary',
        description: 'Сводка по корпусу: сколько произведений на сайте, сколько в основных сборниках, '
            . 'сколько черновиков в Мастерской, сколько записей в блоге. Вызывать первым, чтобы понять масштаб.',
    )]
    public function corpusSummary(): array
    {
        $groups = $this->workGroupRepository->findAllActiveSorted();
        $countsByGroup = $this->workRepository->countActiveByGroup();

        $inFavorites = 0;
        foreach ($groups as $group) {
            if ($group->getIsFavorite()) {
                $inFavorites += $countsByGroup[(int) $group->getId()] ?? 0;
            }
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'works_active' => $this->workRepository->count(['isActive' => true]),
            'works_in_favorite_groups' => $inFavorites,
            'groups_active' => count($groups),
            'drafts' => $this->poemRepository->countByStatus(PoemStatus::Draft),
            'trash' => $this->poemRepository->countByStatus(PoemStatus::Trash),
            'blog_posts' => $this->blogPostRepository->count(['isActive' => true]),
            'notes' => 'Разделы с is_favorite = true — основные сборники стихов (архив с 2010 года). '
                . 'Разделы «Плохие стихи…» — отбракованное автором. Черновики — свежее, ещё не на сайте.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[McpTool(
        name: 'list_groups',
        description: 'Список разделов (сборников) сайта с числом произведений. '
            . 'Без parent_id — все активные разделы; с parent_id — только дочерние указанного.',
    )]
    public function listGroups(
        #[Schema(description: 'Id родительского раздела; пусто — все разделы')]
        ?int $parentId = null,
    ): array {
        $countsByGroup = $this->workRepository->countActiveByGroup();
        $result = [];

        foreach ($this->workGroupRepository->findAllActiveSorted() as $group) {
            $groupParentId = $group->getParent()?->getId();
            if ($parentId !== null && $groupParentId !== $parentId) {
                continue;
            }

            $result[] = $this->formatGroup($group, $countsByGroup[(int) $group->getId()] ?? 0);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatGroup(WorkGroup $group, int $worksCount): array
    {
        return [
            'id' => $group->getId(),
            'parent_id' => $group->getParent()?->getId(),
            'title' => $group->getTitle(),
            'period' => $group->getComment(),
            'is_favorite' => $group->getIsFavorite(),
            'works_count' => $worksCount,
        ];
    }
}
