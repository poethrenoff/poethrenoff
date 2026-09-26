<?php

namespace App\Mcp;

use App\Entity\Work;
use App\Repository\WorkRepository;
use App\Service\WorkService;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

/**
 * MCP-инструменты по произведениям сайта. Только чтение.
 */
class WorkTools
{
    private const int MAX_LIMIT = 50;

    public function __construct(
        private WorkRepository $workRepository,
        private WorkService $workService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_work',
        description: 'Произведение с сайта целиком по id: текст, дата, раздел, соседи по разделу.',
    )]
    public function getWork(
        #[Schema(description: 'Id произведения на сайте')]
        int $id,
    ): array {
        $work = $this->workRepository->findOneActiveById($id);
        if ($work === null) {
            return ['error' => sprintf('Произведение %d не найдено', $id)];
        }

        $neighbours = $this->workRepository->findPrevNext($work);

        $result = $this->formatWork($work, true);
        $result['prev_id'] = $neighbours['prev']?->getId();
        $result['next_id'] = $neighbours['next']?->getId();

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'search_works',
        description: 'Поиск по заголовкам и текстам произведений сайта. Все слова запроса должны встретиться. '
            . 'Возвращает страницу результатов без полного текста; текст — через get_work.',
    )]
    public function searchWorks(
        #[Schema(description: 'Слова для поиска, через пробел')]
        string $query,
        #[Schema(description: 'Искать только в основных сборниках (is_favorite)')]
        bool $favoritesOnly = false,
        #[Schema(description: 'Номер страницы, с 1')]
        int $page = 1,
        #[Schema(description: 'Размер страницы, не больше 50')]
        int $limit = 20,
    ): array {
        $query = trim($query);
        if ($query === '') {
            return ['error' => 'Пустой запрос'];
        }

        $page = max(1, $page);
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        $pagination = $this->workRepository->search($query, $page, $limit, $favoritesOnly);
        $items = $pagination->getItems();
        $works = is_array($items) ? $items : iterator_to_array($items);
        $total = $pagination->getTotalItemCount();

        return [
            'query' => $query,
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / $limit),
            'items' => array_values(array_map(
                fn (Work $work): array => $this->formatWork($work, false) + [
                    'snippet' => $this->snippet($work->getText(), $query),
                ],
                $works,
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatWork(Work $work, bool $withText): array
    {
        $group = $work->getGroup();
        $dateRaw = $work->getComment();
        $date = $dateRaw === null ? null : $this->workService->parseCommentDate(trim($dateRaw));

        $result = [
            'id' => $work->getId(),
            'title' => $work->getDisplayTitle(),
            'date' => $date?->format('Y-m-d'),
            'date_raw' => $dateRaw,
            'group_id' => $group?->getId(),
            'group_title' => $group?->getTitle(),
            'is_favorite' => $group?->getIsFavorite() ?? false,
            'url' => 'https://poethrenoff.ru/work/view/' . $work->getId(),
        ];

        if ($withText) {
            $result['text'] = $work->getText();
        }

        return $result;
    }

    /**
     * Первая строка текста, где встречается любое слово запроса; иначе — первая строка.
     */
    private function snippet(string $text, string $query): string
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $words = array_filter(explode(' ', mb_strtolower($query)));

        foreach ($lines as $line) {
            $lower = mb_strtolower($line);
            foreach ($words as $word) {
                if (mb_strpos($lower, $word) !== false) {
                    return trim($line);
                }
            }
        }

        return trim($lines[0] ?? '');
    }
}
