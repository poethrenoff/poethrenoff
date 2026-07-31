<?php

namespace App\Service;

use App\Entity\Audio;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadService
{
    private const array ALLOWED_EXTENSIONS = ['webm', 'mp3', 'ogg', 'wav'];

    private string $audioDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        #[Autowire('%app.site_context%')]
        private string $siteContext,
    ) {
        $this->audioDir = $this->projectDir . '/htdocs/' . $this->siteContext . '/upload/audio';
    }

    public function getAllowedExtensions(): array
    {
        return self::ALLOWED_EXTENSIONS;
    }

    public function getAudioDir(): string
    {
        return $this->audioDir;
    }

    public function uploadFile(UploadedFile $file): string
    {
        $extension = strtolower($file->guessExtension() ?? '');
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(
                'Допустимые форматы: ' . implode(', ', self::ALLOWED_EXTENSIONS)
            );
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->move($this->audioDir, $fileName);

        return $fileName;
    }

    public function replaceFile(Audio $audio, UploadedFile $file): string
    {
        $newFileName = $this->uploadFile($file);

        $oldFileName = basename($audio->getFilePath());
        $oldPath = $this->audioDir . '/' . $oldFileName;
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }

        return $newFileName;
    }

    public function deleteFile(string $filePath): void
    {
        $fileName = basename($filePath);
        $path = $this->audioDir . '/' . $fileName;
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function deleteAudioFile(Audio $audio): void
    {
        $this->deleteFile($audio->getFilePath());
    }
}
