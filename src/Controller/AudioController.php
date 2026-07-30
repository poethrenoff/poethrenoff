<?php

namespace App\Controller;

use App\Entity\Audio;
use App\Repository\AudioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use App\Traits\CsrfTrait;

#[Route(condition: "request.server.get('APP_SITE_CONTEXT') == 'work'")]
#[IsGranted('ROLE_ADMIN')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class AudioController extends AbstractController
{
    use CsrfTrait;

    private const array ALLOWED_EXTENSIONS = ['webm', 'mp3', 'ogg', 'wav'];

    private string $audioDir;

    public function __construct(
        private AudioRepository $audioRepository,
        private EntityManagerInterface $entityManager,
        private CsrfTokenManagerInterface $csrfTokenManager,
        #[Autowire('%app.site_context%')]
        private string $siteContext,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        $this->audioDir = $this->projectDir . '/htdocs/' . $this->siteContext . '/upload/audio';
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
        $path = $this->audioDir . '/' . $fileName;

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
        if (!$file instanceof UploadedFile) {
             return $this->json(['error' => ['message' => 'Файл не получен']], Response::HTTP_BAD_REQUEST);
        }

        $extension = strtolower($file->guessExtension() ?: '');
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return $this->json([
                'error' => ['message' => 'Допустимые форматы: ' . implode(', ', self::ALLOWED_EXTENSIONS)],
            ], Response::HTTP_BAD_REQUEST);
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->move($this->audioDir, $fileName);

        $title = $request->request->get('title', 'Новая запись');

        $audio = new Audio();
        $audio->setTitle($title);
        $audio->setFilePath('/upload/audio/' . $fileName);

        // Duration can be sent from client or calculated
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
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => ['message' => 'Файл не получен']], Response::HTTP_BAD_REQUEST);
        }

        $extension = strtolower($file->guessExtension() ?: '');
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return $this->json([
                'error' => ['message' => 'Допустимые форматы: ' . implode(', ', self::ALLOWED_EXTENSIONS)],
            ], Response::HTTP_BAD_REQUEST);
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->move($this->audioDir, $fileName);

        $oldFileName = basename($audio->getFilePath());
        $oldPath = $this->audioDir . '/' . $oldFileName;
        if (file_exists($oldPath)) {
            unlink($oldPath);
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

        $fileName = basename($audio->getFilePath());
        $path = $this->audioDir . '/' . $fileName;
        if (file_exists($path)) {
            unlink($path);
        }

        $this->entityManager->remove($audio);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }
}
