<?php

namespace App\Service;

use App\Entity\Tag;
use App\Entity\WorkGroup;
use App\Repository\BlogPostRepository;
use App\Repository\WorkRepository;
use Doctrine\Common\Collections\Collection;
use Knp\Component\Pager\Pagination\PaginationInterface;

/**
 * @phpstan-type BlogPostResult array{
 *     id: int|null,
 *     publishedAt: \DateTimeImmutable,
 *     content: string,
 *     tags: Collection<int, Tag>,
 * }
 * @phpstan-type WorkResult array{id: int|null, title: string, group: WorkGroup|null, snippet: string}
 * @phpstan-type PaginationResult array{total_pages: int, current_page: int, prev_page: int|null, next_page: int|null}
 */
class SearchService
{
    public function __construct(
        private WorkRepository $workRepository,
        private BlogPostRepository $blogPostRepository,
    ) {
    }

    /**
     * @return array{results: list<WorkResult>, pagination: PaginationResult}
     */
    public function searchWorks(string $query, int $page = 1, int $limit = 10): array
    {
        $searchPagination = $this->workRepository->search($query, $page, $limit);

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
            'pagination' => $this->buildPagination($searchPagination),
        ];
    }

    /**
     * @return array{results: list<BlogPostResult>, pagination: PaginationResult}
     */
    public function searchBlogPostsByText(string $text, int $page = 1, int $limit = 10): array
    {
        $searchPagination = $this->blogPostRepository->searchByText($text, $page, $limit);

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
            'pagination' => $this->buildPagination($searchPagination),
        ];
    }

    /**
     * @return array{results: list<BlogPostResult>, pagination: PaginationResult}
     */
    public function searchBlogPostsByTag(string $tag, int $page = 1, int $limit = 10): array
    {
        $searchPagination = $this->blogPostRepository->findActiveByTagPaginated($tag, $page, $limit);

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
            'pagination' => $this->buildPagination($searchPagination),
        ];
    }

    /**
     * @param PaginationInterface<int, mixed> $pagination
     * @return PaginationResult
     */
    public function buildPagination(PaginationInterface $pagination): array
    {
        $totalItems = $pagination->getTotalItemCount();
        $itemsPerPage = $pagination->getItemNumberPerPage();
        $currentPage = $pagination->getCurrentPageNumber();
        $totalPages = (int) ceil($totalItems / $itemsPerPage);

        return [
            'total_pages' => $totalPages,
            'current_page' => $currentPage,
            'prev_page' => $currentPage > 1 ? $currentPage - 1 : null,
            'next_page' => $currentPage < $totalPages ? $currentPage + 1 : null,
        ];
    }
}
