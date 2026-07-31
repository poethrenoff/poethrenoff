<?php

namespace App\Controller;

use App\Entity\WorkGroup;
use App\Entity\WorkVote;
use App\Enum\VoteType;
use App\Entity\WorkComment;
use App\Repository\StaticTextRepository;
use App\Repository\WorkGroupRepository;
use App\Repository\WorkRepository;
use App\Repository\WorkVoteRepository;
use App\Repository\PictureRepository;
use App\Repository\WorkCommentRepository;
use App\Service\CommentService;
use App\Service\SearchService;
use App\Trait\CsrfTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SiteController extends AbstractController
{
    use CsrfTrait;

    public function __construct(
        private StaticTextRepository $staticTextRepository,
        private WorkGroupRepository $workGroupRepository,
        private WorkRepository $workRepository,
        private PictureRepository $pictureRepository,
        private WorkVoteRepository $workVoteRepository,
        private WorkCommentRepository $workCommentRepository,
        private EntityManagerInterface $entityManager,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private SearchService $searchService,
        private CommentService $commentService,
    ) {
    }

    #[Route('/', name: 'site_homepage')]
    public function homepage(): Response
    {
        $favoriteGroups = $this->workGroupRepository->findFavoriteActiveSorted();
        return $this->render('site/index.html.twig', [
            'favoriteGroups' => $favoriteGroups,
        ]);
    }

    #[Route('/about', name: 'site_about')]
    public function about(): Response
    {
        $aboutContent = $this->staticTextRepository->findOneBySlug('about');
        return $this->render('site/about.html.twig', [
            'aboutContent' => $aboutContent,
        ]);
    }

    #[Route('/work', name: 'site_work_index')]
    public function allSections(): Response
    {
        $groups = $this->workGroupRepository->findAllActiveSorted();
        $tree = $this->buildTree($groups);

        return $this->render('site/group.html.twig', [
            'group' => null,
            'tree' => $tree,
            'works' => null,
            'breadcrumbs' => $this->buildBreadcrumbs(),
        ]);
    }

    #[Route('/work/group/{id}', name: 'site_group')]
    public function group(int $id): Response
    {
        $groups = $this->workGroupRepository->findAllActiveSorted();
        $group = $this->workGroupRepository->find($id);
        if (!$group) {
            throw $this->createNotFoundException('Group not found');
        }

        $tree = $this->buildTree($groups, $id);
        $breadcrumbs = $this->buildBreadcrumbs($group);

        $works = $this->workRepository->findActiveByGroup($group);

        return $this->render('site/group.html.twig', [
            'group' => $group,
            'tree' => $tree,
            'works' => $works,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    #[Route('/work/view/{id}', name: 'site_work')]
    public function work(int $id): Response
    {
        $work = $this->workRepository->findOneActiveById($id);
        if (!$work) {
            throw $this->createNotFoundException('Work not found or not active.');
        }

        $group = $work->getGroup();
        $breadcrumbs = [];
        if ($group) {
            $breadcrumbs = $this->buildBreadcrumbs($group);
        }

        $prevNext = $this->workRepository->findPrevNext($work);

        $allComments = $this->workCommentRepository->findActiveByWork($work);
        $rootComments = $this->commentService->buildCommentTree($allComments);

        return $this->render('site/work.html.twig', [
            'work' => $work,
            'rootComments' => $rootComments,
            'prev_work' => $prevNext['prev'],
            'next_work' => $prevNext['next'],
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    #[Route('/picture/{page}', name: 'site_picture', defaults: ['page' => 1])]
    public function picture(int $page): Response
    {
        $pictures = $this->pictureRepository->findActivePaginated($page, 24);
        $pagination = $this->searchService->buildPagination($pictures);

        return $this->render('site/picture.html.twig', [
            'pictures' => $pictures,
            'pagination' => $pagination,
        ]);
    }

    #[Route('/search', name: 'site_search')]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q');
        $page = $request->query->getInt('page', 1);

        $searchResults = [];
        $pagination = null;

        if (!empty($query)) {
            $result = $this->searchService->searchWorks($query, $page, 10);
            $searchResults = $result['results'];
            $pagination = $result['pagination'];
        }

        return $this->render('site/search.html.twig', [
            'query' => $query,
            'searchResults' => $searchResults,
            'pagination' => $pagination,
        ]);
    }

    #[Route('/work/random', name: 'site_random')]
    public function random(): Response
    {
        $randomWork = $this->workRepository->findRandomActiveFromFavorites();

        if (!$randomWork) {
            throw $this->createNotFoundException('No works available.');
        }

        $group = $randomWork->getGroup();
        $breadcrumbs = [];
        if ($group) {
            $breadcrumbs = $this->buildBreadcrumbs($group);
        }

        $prevNext = $this->workRepository->findPrevNext($randomWork);

        $allComments = $this->workCommentRepository->findActiveByWork($randomWork);
        $rootComments = $this->commentService->buildCommentTree($allComments);

        return $this->render('site/work.html.twig', [
            'work' => $randomWork,
            'rootComments' => $rootComments,
            'prev_work' => $prevNext['prev'],
            'next_work' => $prevNext['next'],
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    #[Route('/work/vote/{id}', name: 'work_vote', methods: ['POST'])]
    public function vote(int $id, Request $request): JsonResponse
    {
        if (!$this->validateCsrf($request, 'work_vote')) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $work = $this->workRepository->find($id);
        if (!$work) {
            return new JsonResponse(['error' => 'Work not found'], Response::HTTP_NOT_FOUND);
        }

        $type = VoteType::tryFrom((string) $request->request->get('type'));
        if (!$type) {
            return new JsonResponse(['error' => 'Invalid vote type'], Response::HTTP_BAD_REQUEST);
        }

        $ip = $request->getClientIp() ?? '127.0.0.1';
        $userAgent = $request->headers->get('User-Agent', '');

        $salt = $this->getParameter('app.vote_salt');
        if (!is_string($salt)) {
            throw new \LogicException('Parameter "app.vote_salt" must be a string');
        }

        $ipHash = hash('sha256', $ip . $salt);
        $userAgentHash = hash('sha256', $userAgent . $salt);

        $existingVote = $this->workVoteRepository->findOneBy([
            'work' => $work,
            'ipHash' => $ipHash,
            'userAgentHash' => $userAgentHash,
        ]);

        if ($existingVote) {
            return new JsonResponse(['error' => 'Already voted'], Response::HTTP_CONFLICT);
        }

        $vote = new WorkVote();
        $vote->setWork($work);
        $vote->setIpHash($ipHash);
        $vote->setUserAgentHash($userAgentHash);
        $vote->setVoteType($type);

        $this->entityManager->persist($vote);

        if ($type === VoteType::Like) {
            $work->setLikesCount($work->getLikesCount() + 1);
        } else {
            $work->setDislikesCount($work->getDislikesCount() + 1);
        }

        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Already voted'], Response::HTTP_CONFLICT);
        }

        return new JsonResponse([
            'likes' => $work->getLikesCount(),
            'dislikes' => $work->getDislikesCount(),
        ]);
    }

    #[Route('/work/comment/{id}', name: 'work_comment_save', methods: ['POST'])]
    public function saveComment(Request $request, int $id): Response
    {
        $work = $this->workRepository->findOneActiveById($id);
        if (!$work) {
            return $this->json(['error' => 'Work not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->validateCsrf($request, 'site_comment')) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        $authorError = $this->commentService->validateAuthor($data['author'] ?? '');
        if ($authorError !== null) {
            return $this->json(['error' => $authorError], Response::HTTP_BAD_REQUEST);
        }

        $author = trim($data['author']);
        $content = $data['content'] ?? '';
        $parentId = $data['parentId'] ?? null;

        $sanitizedContent = $this->commentService->sanitizeContent($content);
        if (empty(strip_tags($sanitizedContent))) {
            return $this->json(['error' => 'Комментарий не может быть пустым'], Response::HTTP_BAD_REQUEST);
        }

        $comment = new WorkComment();
        $comment->setWork($work);
        $comment->setAuthor($author);
        $comment->setContent($sanitizedContent);
        $comment->setCreatedAt(new \DateTimeImmutable());
        $comment->setIsActive(true);
        $comment->setInfo(sprintf('%s, %s', $request->getClientIp(), $request->headers->get('User-Agent')));

        if ($parentId) {
            $parent = $this->workCommentRepository->find($parentId);
            if ($parent && $parent->getWork()?->getId() === $work->getId()) {
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

    /**
     * @return list<array{label: string, path: string}>
     */
    private function buildBreadcrumbs(?WorkGroup $group = null): array
    {
        $breadcrumbs = [];
        $path = [];
        $currentGroup = $group;

        while ($currentGroup) {
            $path[] = $currentGroup;
            $currentGroup = $currentGroup->getParent();
        }

        $breadcrumbs[] = ['label' => 'Все', 'path' => $this->generateUrl('site_work_index')];

        foreach (array_reverse($path) as $ancestor) {
            $breadcrumbs[] = [
                'label' => $ancestor->getTitle(),
                'path' => $this->generateUrl('site_group', ['id' => $ancestor->getId()]),
            ];
        }

        return $breadcrumbs;
    }

    /**
     * @param list<WorkGroup> $groups
     * @return list<array{group: WorkGroup, children: list<mixed>}>
     */
    private function buildTree(array $groups, ?int $parentId = null): array
    {
        $tree = [];
        foreach ($groups as $group) {
            if ($group->getParent()?->getId() === $parentId) {
                $children = $this->buildTree($groups, $group->getId());
                $tree[] = [
                    'group' => $group,
                    'children' => $children,
                ];
            }
        }
        return $tree;
    }
}
