<?php

namespace App\Controller;

use App\Entity\Poem;
use App\Enum\PoemStatus;
use App\Repository\PoemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(condition: "request.server.get('APP_SITE_CONTEXT') == 'work'")]
#[IsGranted('ROLE_ADMIN')]
class WorkController extends AbstractController
{
    public function __construct(
        private PoemRepository $poemRepository,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/', name: 'work_homepage')]
    public function index(Request $request): Response
    {
        return $this->render('work/index.html.twig');
    }

    #[Route('/poems/', name: 'work_api_poems_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status', 'draft');
        $poems = $this->poemRepository->findByStatus($status);
        $total = $this->poemRepository->countByStatus($status);

        return $this->json([
            'results' => $poems,
            'total' => $total
        ], context: ['groups' => 'poem:list']);
    }

    #[Route('/poems/', name: 'work_api_poems_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || empty(trim($data['content'] ?? ''))) {
            return $this->json(['error' => ['message' => 'Некорректные данные']], Response::HTTP_BAD_REQUEST);
        }

        $content = trim($data['content']);
        $comment = isset($data['comment']) ? trim($data['comment']) : null;

        if ($comment === '') {
            $comment = null;
        }

        $position = $this->poemRepository->findNextPosition(
            (float) ($data['position'] ?? $this->poemRepository->findFirstPosition())
        );

        $poem = new Poem();
        $poem->setContent($content);
        $poem->setComment($comment);
        $poem->setStatus(PoemStatus::Draft);
        $poem->setPosition($position);

        $providedTitle = isset($data['title']) ? trim($data['title']) : null;
        $poem->setTitle($providedTitle !== '' ? $providedTitle : null);
        if ($poem->getTitle() === null) {
            $firstLine = $poem->getFirstLine();
            if ($firstLine !== '') {
                $poem->setTitle($firstLine);
            }
        }

        $this->em->persist($poem);
        $this->em->flush();

        return $this->json($poem, Response::HTTP_CREATED, context: ['groups' => 'poem:detail']);
    }

    #[Route('/poems/stats/', name: 'work_api_poems_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return $this->json([
            'total' => $this->poemRepository->countActive(),
            'trash' => $this->poemRepository->countByStatus(PoemStatus::Trash->value),
        ]);
    }

    #[Route('/poems/{id}/', name: 'work_api_poems_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(Poem $poem): JsonResponse
    {
        return $this->json($poem, context: ['groups' => 'poem:detail']);
    }

    #[Route('/poems/{id}/', name: 'work_api_poems_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Poem $poem): JsonResponse
    {
        if ($poem->getStatus() === PoemStatus::Trash) {
            return $this->json(['error' => ['message' => 'Нельзя редактировать удалённый стих']], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => ['message' => 'Некорректные данные']], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('title', $data)) {
            $poem->setTitle($data['title'] !== '' ? trim($data['title']) : null);
        }
        if (array_key_exists('content', $data)) {
            $poem->setContent($data['content'] ?? '');
        }
        if (array_key_exists('comment', $data)) {
            $poem->setComment($data['comment'] !== '' ? trim($data['comment']) : null);
        }

        $poem->createSnapshot();

        if (!empty($data['title']) && $poem->getTitle() === null) {
            $poem->setTitle(trim($data['title']));
        }

        $this->em->flush();

        return $this->json($poem, context: ['groups' => 'poem:detail']);
    }

    #[Route('/poems/{id}/trash/', name: 'work_api_poems_trash', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function trash(Poem $poem): JsonResponse
    {
        if ($poem->getStatus() === PoemStatus::Trash) {
            return $this->json(['error' => ['message' => 'Стих уже в корзине']], Response::HTTP_BAD_REQUEST);
        }

        $poem->trash();
        $this->em->flush();

        return $this->json(['status' => 'ok']);
    }

    #[Route('/poems/{id}/restore/', name: 'work_api_poems_restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restore(Poem $poem): JsonResponse
    {
        if ($poem->getStatus() !== PoemStatus::Trash || $poem->getDeletedAt() === null) {
            return $this->json(['error' => ['message' => 'Стих уже восстановлен']], Response::HTTP_BAD_REQUEST);
        }

        $poem->restore();
        $this->em->flush();

        return $this->json(['status' => 'ok']);
    }

    #[Route('/poems/{id}/reorder/', name: 'work_api_poems_reorder', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reorder(Request $request, Poem $poem): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => ['message' => 'Некорректные данные']], Response::HTTP_BAD_REQUEST);
        }

        $beforeId = isset($data['before_id']) ? (int) $data['before_id'] : null;
        $afterId = isset($data['after_id']) ? (int) $data['after_id'] : null;

        $before = $beforeId !== null ? $this->poemRepository->find($beforeId) : null;
        $after = $afterId !== null ? $this->poemRepository->find($afterId) : null;

        if ($before !== null && $after !== null) {
            $poem->setPosition(($before->getPosition() + $after->getPosition()) / 2);
        } elseif ($before !== null) {
            $poem->setPosition($before->getPosition() - 1.0);
        } elseif ($after !== null) {
            $poem->setPosition($after->getPosition() + 1.0);
        } else {
            $poem->setPosition($this->poemRepository->findNextPosition($this->poemRepository->findFirstPosition()));
        }

        $this->em->flush();

        return $this->json($poem, context: ['groups' => 'poem:detail']);
    }

    #[Route('/poems/{id}/', name: 'work_api_poems_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Poem $poem): JsonResponse
    {
        if ($poem->getStatus() !== PoemStatus::Trash || $poem->getDeletedAt() === null) {
            return $this->json(['error' => ['message' => 'Можно удалить навсегда только стих из корзины']], Response::HTTP_BAD_REQUEST);
        }

        $this->em->remove($poem);
        $this->em->flush();

        return $this->json(['status' => 'ok']);
    }
}
