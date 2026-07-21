<?php

namespace App\Controller;

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
        $pagination = $this->postRepository->findActivePaginated($page, 10);

        $response = $this->render('blog/index.html.twig', [
            'pagination' => $pagination,
            'pagination_data' => $this->getPaginationData($pagination),
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
        
        $pagination = null;
        $paginationData = null;
        $searchResults = [];

        if ($tag) {
            $pagination = $this->postRepository->findActiveByTagPaginated($tag, $page, 10);
            $paginationData = $this->getPaginationData($pagination);
            foreach ($pagination as $post) {
                $searchResults[] = [
                    'id' => $post->getId(),
                    'publishedAt' => $post->getPublishedAt(),
                    'content' => $post->getContent(),
                    'tags' => $post->getTags(),
                ];
            }
        } elseif ($text) {
            $pagination = $this->postRepository->searchByText($text, $page, 10);
            $paginationData = $this->getPaginationData($pagination);
            
            $words = explode(' ', $text);
            $words = array_filter($words);

            foreach ($pagination as $post) {
                $content = $post->getContent();
                foreach ($words as $word) {
                    $content = preg_replace('/(' . preg_quote($word, '/') . ')/ui', '<mark>$1</mark>', $content);
                }

                $searchResults[] = [
                    'id' => $post->getId(),
                    'publishedAt' => $post->getPublishedAt(),
                    'content' => $content,
                    'tags' => $post->getTags(),
                ];
            }
        }

        $tags = $this->tagRepository->findBy([], ['title' => 'ASC']);

        $response = $this->render('blog/search.html.twig', [
            'searchResults' => $searchResults,
            'pagination_data' => $paginationData,
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

    private function getPaginationData(PaginationInterface $pagination): array
    {
        $data = $pagination->getPaginationData();
        return [
            'total_pages' => $data['pageCount'],
            'current_page' => $data['current'],
            'prev_page' => $data['previous'] ?? null,
            'next_page' => $data['next'] ?? null,
        ];
    }
}
