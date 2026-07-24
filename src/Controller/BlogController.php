<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Repository\BlogPostRepository;
use App\Repository\BlogCommentRepository;
use App\Repository\TagRepository;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.server.get('APP_SITE_CONTEXT') == 'blog'")]
class BlogController extends AbstractController
{
    public function __construct(
        private BlogPostRepository $postRepository,
        private BlogCommentRepository $commentRepository,
        private TagRepository $tagRepository,
    ) {}

    #[Route('/', name: 'blog_homepage')]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $postResults = $this->postRepository->findActivePaginated($page, 10);
        $paginationData = $postResults->getPaginationData();

        $pagination = [
            'total_pages' => $paginationData['pageCount'],
            'current_page' => $paginationData['current'],
            'prev_page' => $paginationData['previous'] ?? null,
            'next_page' => $paginationData['next'] ?? null,
        ];

        $response = $this->render('blog/index.html.twig', [
            'postResults' => $postResults,
            'pagination' => $pagination,
        ]);

        return $this->addNoindexHeader($response);
    }

    #[Route('/post/{id}', name: 'blog_post')]
    public function post(int $id): Response
    {
        $post = $this->postRepository->findOneActiveById($id);
        if (!$post) {
            throw $this->createNotFoundException('Post not found');
        }

        $allComments = $this->commentRepository->findActiveByPost($post);
        $rootComments = array_filter($allComments, fn($c) => $c->getParent() === null);

        $response = $this->render('blog/post.html.twig', [
            'post' => $post,
            'rootComments' => $rootComments,
        ]);

        return $this->addNoindexHeader($response);
    }

    #[Route('/search', name: 'blog_search')]
    public function search(Request $request): Response
    {
        $text = $request->query->get('text');
        $tag = $request->query->get('tag');
        $page = $request->query->getInt('page', 1);

        $searchResults = [];
        $pagination = null;

        if ($tag) {
            $searchPagination = $this->postRepository->findActiveByTagPaginated($tag, $page, 10);
            $paginationData = $searchPagination->getPaginationData();
            foreach ($searchPagination as $post) {
                $searchResults[] = [
                    'id' => $post->getId(),
                    'publishedAt' => $post->getPublishedAt(),
                    'content' => $post->getContent(),
                    'tags' => $post->getTags(),
                ];
            }

            $pagination = [
                'total_pages' => $paginationData['pageCount'],
                'current_page' => $paginationData['current'],
                'prev_page' => $paginationData['previous'] ?? null,
                'next_page' => $paginationData['next'] ?? null,
            ];
        } elseif ($text) {
            $searchPagination = $this->postRepository->searchByText($text, $page, 10);
            $paginationData = $searchPagination->getPaginationData();

            $words = explode(' ', $text);
            $words = array_filter($words);

            foreach ($searchPagination as $post) {
                $content = $post->getContent();
                foreach ($words as $word) {
                    $content = preg_replace('/(' . preg_quote($word, '/') . ')/ui', '<b>$1</b>', $content);
                }

                $searchResults[] = [
                    'id' => $post->getId(),
                    'publishedAt' => $post->getPublishedAt(),
                    'content' => $content,
                    'tags' => $post->getTags(),
                ];
            }

            $pagination = [
                'total_pages' => $paginationData['pageCount'],
                'current_page' => $paginationData['current'],
                'prev_page' => $paginationData['previous'] ?? null,
                'next_page' => $paginationData['next'] ?? null,
            ];
        }

        $tags = $this->tagRepository->findTagCloud(50);

        $response = $this->render('blog/search.html.twig', [
            'searchResults' => $searchResults,
            'pagination' => $pagination,
            'tags' => $tags,
            'currentTag' => $tag,
            'searchText' => $text,
        ]);

        return $this->addNoindexHeader($response);
    }

    private function addNoindexHeader(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        return $response;
    }
}
