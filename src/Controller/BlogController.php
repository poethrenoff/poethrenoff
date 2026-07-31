<?php

namespace App\Controller;

use App\Repository\BlogPostRepository;
use App\Repository\BlogCommentRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\Entity\BlogComment;
use App\Traits\CommentUtilsTrait;
use App\Traits\CsrfTrait;

class BlogController extends AbstractController
{
    use CommentUtilsTrait;
    use CsrfTrait;

    public function __construct(
        private BlogPostRepository $postRepository,
        private BlogCommentRepository $commentRepository,
        private TagRepository $tagRepository,
        private EntityManagerInterface $entityManager,
        private HtmlSanitizerInterface $htmlSanitizer,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

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
        $rootComments = $this->buildCommentTree($allComments);

        $prevNext = $this->postRepository->findPrevNext($post);

        $response = $this->render('blog/post.html.twig', [
            'post' => $post,
            'rootComments' => $rootComments,
            'prev_post' => $prevNext['prev'],
            'next_post' => $prevNext['next'],
        ]);

        return $this->addNoindexHeader($response);
    }

    #[Route('/post/{id}/comment', name: 'blog_comment_save', methods: ['POST'])]
    public function saveComment(Request $request, int $id): Response
    {
        $post = $this->postRepository->findOneActiveById($id);
        if (!$post) {
            return $this->json(['error' => 'Post not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->validateCsrf($request, 'blog_comment')) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        $author = trim($data['author'] ?? '');
        $content = $data['content'] ?? '';
        $parentId = $data['parentId'] ?? null;

        if (empty($author)) {
            return $this->json(['error' => 'Имя обязательно'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($author) > 100) {
            return $this->json(['error' => 'Имя не должно превышать 100 символов'], Response::HTTP_BAD_REQUEST);
        }

        $sanitizedContent = $this->htmlSanitizer->sanitize($this->autolink($content));
        if (empty(strip_tags($sanitizedContent))) {
            return $this->json(['error' => 'Комментарий не может быть пустым'], Response::HTTP_BAD_REQUEST);
        }

        $comment = new BlogComment();
        $comment->setPost($post);
        $comment->setAuthor($author);
        $comment->setContent($sanitizedContent);
        $comment->setCreatedAt(new \DateTimeImmutable());
        $comment->setIsActive(true);
        $comment->setInfo(sprintf('%s, %s', $request->getClientIp(), $request->headers->get('User-Agent')));

        if ($parentId) {
            $parent = $this->commentRepository->find($parentId);
            if ($parent && $parent->getPost()->getId() === $post->getId()) {
                $comment->setParent($parent);
            }
        }

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $this->json([
            'id' => $comment->getId(),
            'author' => $comment->getAuthor(),
            'content' => $comment->getContent(),
            'createdAt' => $comment->getCreatedAt()->format('d.m.Y H:i'),
            'parentId' => $comment->getParent() ? $comment->getParent()->getId() : null,
        ]);
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
