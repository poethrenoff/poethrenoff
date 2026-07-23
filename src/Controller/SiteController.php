<?php

namespace App\Controller;

use App\Repository\StaticTextRepository;
use App\Repository\WorkGroupRepository;
use App\Repository\WorkRepository;
use App\Repository\PictureRepository;
use App\Entity\WorkGroup;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.server.get('APP_SITE_CONTEXT') == 'www'")]
class SiteController extends AbstractController
{
    private StaticTextRepository $staticTextRepository;
    private WorkGroupRepository $workGroupRepository;
    private WorkRepository $workRepository;
    private PictureRepository $pictureRepository;

    public function __construct(
        StaticTextRepository $staticTextRepository,
        WorkGroupRepository $workGroupRepository,
        WorkRepository $workRepository,
        PictureRepository $pictureRepository
    ) {
        $this->staticTextRepository = $staticTextRepository;
        $this->workGroupRepository = $workGroupRepository;
        $this->workRepository = $workRepository;
        $this->pictureRepository = $pictureRepository;
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

        $works = [];
        if (!$hasChildren) {
            $works = $this->workRepository->findActiveByGroup($group, 1, 100);
        }

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
        $searchResults = [];
        $pagination = null;

        if (!empty($query)) {
            $searchPagination = $this->workRepository->search($query, $request->query->getInt('page', 1), 10);
            $paginationData = $searchPagination->getPaginationData();

            $words = explode(' ', $query);
            $words = array_filter($words);

            $searchResults = [];
            /** @var \App\Entity\Work $work */
            foreach ($searchPagination->getItems() as $work) {
                $text = strip_tags($work->getText());
                $snippet = mb_substr($text, 0, 300);

                foreach ($words as $word) {
                    $snippet = preg_replace('/(' . preg_quote($word, '/') . ')/ui', '<mark>$1</mark>', $snippet);
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
