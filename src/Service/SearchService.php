<?php

namespace App\Service;

use App\Repository\BlogPostRepository;
use App\Repository\WorkRepository;

class SearchService
{
    public function __construct(
        private WorkRepository $workRepository,
        private BlogPostRepository $blogPostRepository,
    ) {
    }

    /**
     * @param string $query
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function searchWorks(string $query, int $page = 1, int $limit = 10): array
    {
        $words = $this->splitQuery($query);
        $searchPagination = $this->workRepository->search($query, $page, $limit);
        $paginationData = $searchPagination->getPaginationData();

        $results = [];
        foreach ($searchPagination->getItems() as $work) {
            $text = strip_tags($work->getText());
            $snippet = mb_substr($text, 0, 300);

            $results[] = [
                'id' => $work->getId(),
                'title' => $work->getTitle(),
                'group' => $work->getGroup(),
                'snippet' => $snippet . '...',
            ];
        }

        return [
            'results' => $results,
            'pagination' => $this->buildPagination($paginationData),
        ];
    }

    /**
     * @param string $text
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function searchBlogPostsByText(string $text, int $page = 1, int $limit = 10): array
    {
        $words = $this->splitQuery($text);
        $searchPagination = $this->blogPostRepository->searchByText($text, $page, $limit);
        $paginationData = $searchPagination->getPaginationData();

        $results = [];
        foreach ($searchPagination as $post) {
            $content = strip_tags($post->getContent());
            $results[] = [
                'id' => $post->getId(),
                'publishedAt' => $post->getPublishedAt(),
                'content' => $content,
                'tags' => $post->getTags(),
            ];
        }

        return [
            'results' => $results,
            'pagination' => $this->buildPagination($paginationData),
        ];
    }

    /**
     * @param string $tag
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function searchBlogPostsByTag(string $tag, int $page = 1, int $limit = 10): array
    {
        $searchPagination = $this->blogPostRepository->findActiveByTagPaginated($tag, $page, $limit);
        $paginationData = $searchPagination->getPaginationData();

        $results = [];
        foreach ($searchPagination as $post) {
            $results[] = [
                'id' => $post->getId(),
                'publishedAt' => $post->getPublishedAt(),
                'content' => $post->getContent(),
                'tags' => $post->getTags(),
            ];
        }

        return [
            'results' => $results,
            'pagination' => $this->buildPagination($paginationData),
        ];
    }

    /**
     * @param string $query
     * @return list<string>
     */
    private function splitQuery(string $query): array
    {
        $words = explode(' ', $query);

        return array_values(array_filter($words));
    }

    /**
     * @param array $paginationData
     * @return array
     */
    private function buildPagination(array $paginationData): array
    {
        return [
            'total_pages' => $paginationData['pageCount'],
            'current_page' => $paginationData['current'],
            'prev_page' => $paginationData['previous'] ?? null,
            'next_page' => $paginationData['next'] ?? null,
        ];
    }
}
