<?php

namespace App\Controller;

use App\Entity\Audio;
use App\Enum\RecognizeTaskStatus;
use App\Repository\AudioRepository;
use App\Repository\RecognizeTaskRepository;
use App\Service\FileUploadService;
use App\Service\RecognizeService;
use App\Trait\CsrfTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class AudioController extends AbstractController
{
    use CsrfTrait;

    public function __construct(
        private AudioRepository $audioRepository,
        private EntityManagerInterface $entityManager,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private FileUploadService $fileUploadService,
        private RecognizeService $recognizeService,
        private RecognizeTaskRepository $recognizeTaskRepository,
    ) {
    }

    #[Route('/audio/', name: 'work_api_audio_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $records = $this->audioRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->json($records, context: ['datetime_format' => 'd.m.Y H:i']);
    }

    #[Route('/audio/{id}', name: 'work_api_audio_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getAudio(Audio $audio): Response
    {
        return $this->fileUploadService->buildAccelRedirectResponse($audio)
            ?? $this->json(['error' => ['message' => 'Файл не найден']], Response::HTTP_NOT_FOUND);
    }

    #[Route('/audio/', name: 'work_api_audio_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->validateCsrf($request, 'work_audio_create')) {
            return $this->json(['error' => ['message' => 'Invalid CSRF token']], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('audio') ?? $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => ['message' => 'Файл не получен']], Response::HTTP_BAD_REQUEST);
        }

        try {
            $fileName = $this->fileUploadService->uploadFile($file);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => ['message' => $e->getMessage()],
            ], Response::HTTP_BAD_REQUEST);
        }

        $title = (string) ($request->request->get('title') ?? 'Новая запись');

        $audio = new Audio();
        $audio->setTitle($title);
        $audio->setFilePath('/upload/audio/' . $fileName);

        $duration = (int) $request->request->get('duration');
        if ($duration > 0) {
            $audio->setDuration($duration);
        }

        $this->entityManager->persist($audio);
        $this->entityManager->flush();

        return $this->json($audio, Response::HTTP_CREATED, context: ['datetime_format' => 'd.m.Y H:i']);
    }

    #[Route('/audio/{id}/download', name: 'work_api_audio_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(Audio $audio): Response
    {
        return $this->fileUploadService->buildAccelRedirectResponse($audio, download: true)
            ?? $this->json(['error' => ['message' => 'Файл не найден']], Response::HTTP_NOT_FOUND);
    }

    #[Route('/audio/{id}/rename', name: 'work_api_audio_rename', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rename(Audio $audio, Request $request): JsonResponse
    {
        if (!$this->validateCsrf($request, 'work_audio_rename')) {
            return $this->json(['error' => ['message' => 'Invalid CSRF token']], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $title = trim($data['title'] ?? '');

        if (empty($title)) {
            return $this->json(['error' => ['message' => 'Название не может быть пустым']], Response::HTTP_BAD_REQUEST);
        }

        $audio->setTitle($title);
        $this->entityManager->flush();

        return $this->json($audio, context: ['datetime_format' => 'd.m.Y H:i']);
    }

    #[Route('/audio/{id}/delete', name: 'work_api_audio_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Request $request, Audio $audio): JsonResponse
    {
        if (!$this->validateCsrf($request, 'work_audio_delete')) {
            return $this->json(['error' => ['message' => 'Invalid CSRF token']], Response::HTTP_FORBIDDEN);
        }

        $this->fileUploadService->deleteAudioFile($audio);

        $this->entityManager->remove($audio);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route(
        '/audio/{id}/recognize/',
        name: 'work_api_audio_recognize',
        requirements: ['id' => '\d+'],
        methods: ['POST']
    )]
    public function recognize(Request $request, Audio $audio): JsonResponse
    {
        if (!$this->validateCsrf($request, 'work_audio_recognize')) {
            return $this->json(['error' => ['message' => 'Invalid CSRF token']], Response::HTTP_FORBIDDEN);
        }

        $task = $this->recognizeService->createTask($audio);
        $status = $this->recognizeService->advanceTask($task);

        if ($status === RecognizeTaskStatus::Error) {
            return $this->json([
                'error' => ['message' => $task->getErrorMessage()],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'task_id' => $task->getId(),
            'status' => $status,
        ], Response::HTTP_CREATED);
    }

    #[Route(
        '/audio/{id}/recognize/{uuid}',
        name: 'work_api_audio_recognize_poll',
        requirements: ['id' => '\d+', 'uuid' => '[0-9a-f]+'],
        methods: ['POST']
    )]
    public function recognizePoll(Request $request, string $uuid, Audio $audio): JsonResponse
    {
        if (!$this->validateCsrf($request, 'work_audio_recognize_poll')) {
            return $this->json(['error' => ['message' => 'Invalid CSRF token']], Response::HTTP_FORBIDDEN);
        }

        $task = $this->recognizeTaskRepository->find($uuid);
        if (!$task || $task->getAudio()->getId() !== $audio->getId()) {
            return $this->json(['error' => ['message' => 'Задача не найдена']], Response::HTTP_NOT_FOUND);
        }

        $status = $this->recognizeService->advanceTask($task);

        return match ($status) {
            RecognizeTaskStatus::Completed => $this->json([
                'status' => 'completed',
                'text' => $task->getResultText(),
            ]),
            RecognizeTaskStatus::Error => $this->json([
                'status' => 'error',
                'error' => $task->getErrorMessage(),
            ]),
            default => $this->json([
                'status' => $status,
            ]),
        };
    }
}
