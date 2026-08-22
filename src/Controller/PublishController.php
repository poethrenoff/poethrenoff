<?php

namespace App\Controller;

use App\Enum\PublicationStatus;
use App\Enum\PublishPlatform;
use App\Repository\PoemRepository;
use App\Repository\PublicationLogRepository;
use App\Service\PublishService;
use App\Service\Publishing\SocialPlatformRegistry;
use App\Trait\CsrfTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class PublishController extends AbstractController
{
    use CsrfTrait;

    public function __construct(
        private SocialPlatformRegistry $registry,
        private PublishService $publishService,
        private PublicationLogRepository $publicationLogRepository,
        private PoemRepository $poemRepository,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(
        '/publish/{id}/platforms/',
        name: 'work_api_publish_platforms',
        requirements: ['id' => '\d+'],
        methods: ['GET']
    )]
    public function platforms(int $id): JsonResponse
    {
        $poem = $this->poemRepository->find($id);

        $result = [];
        foreach ($this->registry->all() as $platform) {
            $statusForPoem = 'none';
            if ($poem !== null) {
                $log = $this->publicationLogRepository->findOneBy([
                    'poem' => $poem,
                    'platform' => $platform->key(),
                ]);
                $statusForPoem = $log !== null ? $log->getStatus()->value : 'none';
            }

            $result[] = [
                'key' => $platform->key()->value,
                'label' => $platform->label(),
                'icon' => $platform->iconName(),
                'configured' => $platform->isConfigured(),
                'status_for_poem' => $statusForPoem,
            ];
        }

        return $this->json($result);
    }

    #[Route('/publish/', name: 'work_api_publish_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->validateCsrf($request, 'work_publish')) {
            return $this->json(['error' => ['message' => 'Invalid CSRF token']], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $poem = is_array($data) ? $this->poemRepository->find((int) ($data['poem_id'] ?? 0)) : null;

        if ($poem === null) {
            return $this->json(['error' => ['message' => 'Стих не найден']], Response::HTTP_NOT_FOUND);
        }

        $platformValue = $data['platform'] ?? null;
        $platform = PublishPlatform::tryFrom((string) $platformValue);

        if ($platform === null) {
            return $this->json(['error' => ['message' => 'Неизвестная платформа']], Response::HTTP_BAD_REQUEST);
        }

        if ($this->publishService->hasSuccess($poem, $platform)) {
            return $this->json([
                'error' => ['message' => 'Стих уже опубликован в этой соцсети'],
            ], Response::HTTP_CONFLICT);
        }

        $log = $this->publishService->publish($poem, $platform);

        if ($log->getStatus() !== PublicationStatus::Success) {
            return $this->json([
                'error' => ['message' => $log->getErrorMessage() ?? 'Ошибка публикации'],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'status' => 'completed',
            'external_url' => $log->getExternalUrl(),
        ], Response::HTTP_CREATED);
    }
}
