<?php

namespace App\Controller;

use App\Entity\Audio;
use App\Repository\AudioRepository;
use App\Service\FileUploadService;
use App\Trait\CsrfTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {
    }

    #[Route('/audio/', name: 'work_api_audio_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $records = $this->audioRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->json($records, context: ['datetime_format' => 'd.m.Y H:i']);
    }

    #[Route('/audio/{id}', name: 'work_api_audio_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getAudio(Audio $audio): Response
    {
        $fileName = basename($audio->getFilePath());
        $path = $this->fileUploadService->getAudioDir() . '/' . $fileName;

        if (!file_exists($path)) {
            return new Response('File not found', Response::HTTP_NOT_FOUND);
        }

        $response = new Response();
        $response->headers->set('X-Accel-Redirect', $audio->getFilePath());
        $response->headers->set('Content-Type', mime_content_type($path) ?: 'application/octet-stream');
        $response->headers->set('Content-Length', (string) filesize($path));
        $response->headers->set('Cache-Control', 'public, max-age=31536000');

        return $response;
    }

    #[Route('/audio/', name: 'work_api_audio_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->validateCsrf($request, 'work_audio_create')) {
            return $this->json(['error' => ['message' => 'Invalid CSRF token']], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('audio') ?? $request->files->get('file');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
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

    #[Route('/audio/{id}/rename', name: 'work_api_audio_rename', methods: ['POST'], requirements: ['id' => '\d+'])]
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

    #[Route('/audio/{id}/rewrite', name: 'work_api_audio_rewrite', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function rewrite(Audio $audio, Request $request): JsonResponse
    {
        if (!$this->validateCsrf($request, 'work_audio_rewrite')) {
            return $this->json(['error' => ['message' => 'Invalid CSRF token']], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('audio') ?? $request->files->get('file');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return $this->json(['error' => ['message' => 'Файл не получен']], Response::HTTP_BAD_REQUEST);
        }

        try {
            $fileName = $this->fileUploadService->replaceFile($audio, $file);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => ['message' => $e->getMessage()],
            ], Response::HTTP_BAD_REQUEST);
        }

        $audio->setFilePath('/upload/audio/' . $fileName);

        $duration = (int) $request->request->get('duration');
        if ($duration > 0) {
            $audio->setDuration($duration);
        }

        $this->entityManager->flush();

        return $this->json($audio, context: ['datetime_format' => 'd.m.Y H:i']);
    }

    #[Route('/audio/{id}/delete', name: 'work_api_audio_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
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
}
