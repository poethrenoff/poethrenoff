<?php

namespace App\Controller;

use App\Repository\StaticTextRepository;
use App\Repository\WorkGroupRepository;
use App\Repository\WorkRepository;
use App\Repository\WorkVoteRepository;
use App\Repository\PictureRepository;
use App\Entity\WorkGroup;
use App\Entity\WorkVote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route(condition: "request.server.get('APP_SITE_CONTEXT') == 'www'")]
class SiteController extends AbstractController
{
    public function __construct(
        private StaticTextRepository $staticTextRepository,
        private WorkGroupRepository $workGroupRepository,
        private WorkRepository $workRepository,
        private PictureRepository $pictureRepository,
        private WorkVoteRepository $workVoteRepository,
        private EntityManagerInterface $entityManager,
        private CsrfTokenManagerInterface $csrfTokenManager,
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

        $hasChildren = false;
        foreach ($groups as $g) {
            if ($g->getParent() && $g->getParent()->getId() === $group->getId()) {
                $hasChildren = true;
                break;
            }
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

        return $this->render('site/work.html.twig', [
            'work' => $work,
            'prev_work' => $prevNext['prev'],
            'next_work' => $prevNext['next'],
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    #[Route('/picture/{page}', name: 'site_picture', defaults: ['page' => 1])]
    public function picture(int $page): Response
    {
        $pictures = $this->pictureRepository->findActivePaginated($page, 24); // 24 per page as per plan
        $paginationData = $pictures->getPaginationData();

        $pagination = [
            'total_pages' => $paginationData['pageCount'],
            'current_page' => $paginationData['current'],
            'prev_page' => $paginationData['previous'] ?? null,
            'next_page' => $paginationData['next'] ?? null,
        ];

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
            $searchPagination = $this->workRepository->search($query, $page, 10);
            $paginationData = $searchPagination->getPaginationData();

            $words = explode(' ', $query);
            $words = array_filter($words);

            $searchResults = [];
            foreach ($searchPagination->getItems() as $work) {
                $text = strip_tags($work->getText());
                $snippet = mb_substr($text, 0, 300);

                foreach ($words as $word) {
                    $snippet = preg_replace('/(' . preg_quote($word, '/') . ')/ui', '<b>$1</b>', $snippet);
                }

                $searchResults[] = [
                    'id' => $work->getId(),
                    'title' => $work->getTitle(),
                    'group' => $work->getGroup(),
                    'snippet' => $snippet . '...',
                ];
            }

            $pagination = [
                'total_pages' => $paginationData['pageCount'],
                'current_page' => $paginationData['current'],
                'prev_page' => $paginationData['previous'] ?? null,
                'next_page' => $paginationData['next'] ?? null,
            ];
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

        return $this->render('site/work.html.twig', [
            'work' => $randomWork,
            'prev_work' => $prevNext['prev'],
            'next_work' => $prevNext['next'],
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    #[Route('/work/vote/{id}', name: 'work_vote', methods: ['POST'])]
    public function vote(int $id, Request $request): JsonResponse
    {
        $token = $request->request->get('_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('work_vote', $token))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $work = $this->workRepository->find($id);
        if (!$work) {
            return new JsonResponse(['error' => 'Work not found'], Response::HTTP_NOT_FOUND);
        }

        $type = $request->request->get('type');
        if (!in_array($type, ['like', 'dislike'])) {
            return new JsonResponse(['error' => 'Invalid vote type'], Response::HTTP_BAD_REQUEST);
        }

        $ip = $request->getClientIp() ?? '127.0.0.1';
        $sessionId = $request->getSession()->getId();

        $secret = $this->getParameter('kernel.secret');
        $ipHash = hash('sha256', $ip . $secret);
        $sessionHash = hash('sha256', $sessionId . $secret);

        $existingVote = $this->workVoteRepository->findOneBy([
            'work' => $work,
            'ipHash' => $ipHash,
            'sessionHash' => $sessionHash,
        ]);

        if ($existingVote) {
            return new JsonResponse(['error' => 'Already voted'], Response::HTTP_CONFLICT);
        }

        $vote = new WorkVote();
        $vote->setWork($work);
        $vote->setIpHash($ipHash);
        $vote->setSessionHash($sessionHash);
        $vote->setVoteType($type);

        $this->entityManager->persist($vote);

        if ($type === 'like') {
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

    /**
     * Helper method to build breadcrumbs for a WorkGroup.
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
            $breadcrumbs[] = ['label' => $ancestor->getTitle(), 'path' => $this->generateUrl('site_group', ['id' => $ancestor->getId()])];
        }

        return $breadcrumbs;
    }

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
