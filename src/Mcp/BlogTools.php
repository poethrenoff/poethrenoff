<?php

namespace App\Mcp;

use App\Entity\BlogComment;
use App\Entity\BlogPost;
use App\Entity\Tag;
use App\Repository\BlogCommentRepository;
use App\Repository\BlogPostRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

/**
 * MCP-инструменты по блогу (lo.blog.poethrenoff.ru). Только чтение.
 */
class BlogTools
{
    private const int MAX_LIMIT = 50;
    private const int PREVIEW_LENGTH = 300;

    public function __construct(
        private BlogPostRepository $blogPostRepository,
        private BlogCommentRepository $blogCommentRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'list_blog_posts',
        description: 'Записи блога, новые первыми, страницами, с коротким превью текста. '
            . 'Можно искать по словам (query) или по тегу (tag); полный текст — через get_blog_post.',
    )]
    public function listBlogPosts(
        #[Schema(description: 'Слова для поиска по тексту; пусто — все записи')]
        ?string $query = null,
        #[Schema(description: 'Тег; пусто — без фильтра по тегу')]
        ?string $tag = null,
        #[Schema(description: 'Номер страницы, с 1')]
        int $page = 1,
        #[Schema(description: 'Размер страницы, не больше 50')]
        int $limit = 20,
    ): array {
        $page = max(1, $page);
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $query = $query === null ? '' : trim($query);
        $tag = $tag === null ? '' : trim($tag);

        $pagination = match (true) {
            $query !== '' => $this->blogPostRepository->searchByText($query, $page, $limit),
            $tag !== '' => $this->blogPostRepository->findActiveByTagPaginated($tag, $page, $limit),
            default => $this->blogPostRepository->findActivePaginated($page, $limit),
        };

        $items = $pagination->getItems();
        $posts = is_array($items) ? $items : iterator_to_array($items);
        $total = $pagination->getTotalItemCount();

        return [
            'query' => $query,
            'tag' => $tag,
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / $limit),
            'items' => array_values(array_map(
                fn (BlogPost $post): array => $this->formatPost($post) + [
                    'preview' => mb_substr($this->plainText($post->getContent()), 0, self::PREVIEW_LENGTH),
                ],
                $posts,
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_blog_post',
        description: 'Запись блога целиком по id: текст (как есть и без разметки), теги, комментарии.',
    )]
    public function getBlogPost(
        #[Schema(description: 'Id записи блога')]
        int $id,
    ): array {
        $post = $this->blogPostRepository->findOneActiveById($id);
        if ($post === null) {
            return ['error' => sprintf('Запись %d не найдена', $id)];
        }

        $result = $this->formatPost($post);
        $result['content'] = $post->getContent();
        $result['text'] = $this->plainText($post->getContent());
        $result['comments'] = array_map(static fn (BlogComment $comment): array => [
            'id' => $comment->getId(),
            'parent_id' => $comment->getParent()?->getId(),
            'author' => $comment->getAuthor(),
            'text' => $comment->getContent(),
            'created_at' => $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $this->blogCommentRepository->findActiveByPost($post));

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPost(BlogPost $post): array
    {
        $tags = array_map(static fn (Tag $tag): string => $tag->getTitle(), $post->getTags()->toArray());
        sort($tags);

        return [
            'id' => $post->getId(),
            'published_at' => $post->getPublishedAt()->format(\DateTimeInterface::ATOM),
            'tags' => array_values($tags),
        ];
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
