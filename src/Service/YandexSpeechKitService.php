<?php

namespace App\Service;

use Aws\S3\S3Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class YandexSpeechKitService
{
    public function __construct(
        #[Autowire(env: 'YANDEX_CLOUD_S3_KEY')] private string $s3Key,
        #[Autowire(env: 'YANDEX_CLOUD_S3_SECRET')] private string $s3Secret,
        #[Autowire(env: 'YANDEX_CLOUD_S3_BUCKET')] private string $s3Bucket,
        #[Autowire(env: 'YANDEX_CLOUD_S3_PREFIX')] private string $s3Prefix,
        #[Autowire(env: 'YANDEX_CLOUD_S3_REGION')] private string $s3Region,
        #[Autowire(env: 'YANDEX_CLOUD_S3_ENDPOINT')] private string $s3Endpoint,
        #[Autowire(env: 'YANDEX_CLOUD_FOLDER_ID')] private string $folderId,
        #[Autowire(env: 'YANDEX_SPEECHKIT_API_KEY')] private string $speechKitApiKey,
    ) {
    }

    public function recognize(string $localFilePath): string
    {
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => $this->s3Region,
            'endpoint' => $this->s3Endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $this->s3Key,
                'secret' => $this->s3Secret,
            ],
        ]);

        $s3Key = ltrim($this->s3Prefix, '/') . '/' . uniqid() . '-' . basename($localFilePath);

        try {
            $stream = fopen($localFilePath, 'rb');
            if ($stream === false) {
                throw new \RuntimeException(
                    'Не удалось открыть файл для чтения'
                );
            }

            $contentType = mime_content_type($localFilePath) ?: 'application/octet-stream';

            $s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key' => $s3Key,
                'Body' => $stream,
                'ACL' => 'public-read',
                'ContentType' => $contentType,
            ]);
            fclose($stream);

            $audioUrl = trim($this->s3Endpoint, '/') . '/' . $this->s3Bucket . '/' . trim($s3Key, '/');

            $operationId = $this->startRecognition($audioUrl);

            return $this->pollForResult($operationId);
        } finally {
            try {
                $s3Client->deleteObject([
                    'Bucket' => $this->s3Bucket,
                    'Key' => $s3Key,
                ]);
            } catch (\Throwable $e) {
            }
        }
    }

    private function startRecognition(string $audioUrl): string
    {
        $url = 'https://stt.api.cloud.yandex.net/stt/v3/recognizeFileAsync';
        $body = json_encode([
            'uri' => $audioUrl,
            'recognitionModel' => [
                'model' => 'general',
                'audioFormat' => [
                    'containerAudio' => [
                        'containerAudioType' => 'OGG_OPUS',
                    ],
                ],
                'languageRestriction' => [
                    'restrictionType' => 'WHITELIST',
                    'languageCode' => [
                        'ru-RU'
                    ],
                ],
                'textNormalization' => [
                    'textNormalization' => 'TEXT_NORMALIZATION_DISABLED',
                ],
            ],
            'speakerLabeling' => [
                'speakerLabeling' => 'SPEAKER_LABELING_DISABLED',
            ],
        ]);

        if ($body === false) {
            throw new \RuntimeException(
                'Ошибка сериализации запроса распознавания'
            );
        }

        $response = $this->curlPost($url, $body);
        if ($response === null) {
            throw new \RuntimeException(
                'Ошибка при запуске распознавания'
            );
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['id'])) {
            throw new \RuntimeException(
                'Некорректный ответ сервиса распознавания'
            );
        }

        return (string) $data['id'];
    }

    private function pollForResult(string $operationId): string
    {
        $startTime = time();
        $maxTimeout = 15;

        while (true) {
            $elapsed = time() - $startTime;
            if ($elapsed >= $maxTimeout) {
                throw new \RuntimeException('Таймаут распознавания');
            }

            $url = 'https://operation.api.cloud.yandex.net/operations/' . urlencode($operationId);
            $response = $this->curlGet($url);
            if ($response === null) {
                throw new \RuntimeException(
                    'Ошибка при проверке статуса распознавания'
                );
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                throw new \RuntimeException(
                    'Некорректный ответ сервиса распознавания'
                );
            }

            if (!empty($data['done'])) {
                if (!empty($data['error'])) {
                    $errorMessage = is_array($data['error'])
                        ? ($data['error']['message']
                            ?? 'Ошибка распознавания')
                        : (string) $data['error'];
                    throw new \RuntimeException($errorMessage);
                }

                return $this->getRecognitionResult($operationId);
            }

            sleep(1);
        }
    }

    private function getRecognitionResult(string $operationId): string
    {
        $url = 'https://stt.api.cloud.yandex.net/stt/v3'
            . '/getRecognition?operation_id=' . urlencode($operationId);
        $response = $this->curlGet($url);
        if ($response === null) {
            throw new \RuntimeException(
                'Ошибка при получении результата распознавания'
            );
        }

        $texts = [];
        $lines = explode("\n", $response);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                continue;
            }

            $result = $data['result'] ?? [];
            if (!is_array($result)) {
                continue;
            }

            $final = $result['final'] ?? [];
            if (!is_array($final)) {
                continue;
            }

            $alternatives = $final['alternatives'] ?? [];
            if (is_iterable($alternatives)) {
                foreach ($alternatives as $alternative) {
                    if (isset($alternative['text'])) {
                        $texts[] = (string) $alternative['text'];
                    }
                }
            }
        }

        if (empty($texts)) {
            throw new \RuntimeException('Пустой результат распознавания');
        }

        return implode(' ', $texts);
    }

    private function curlGet(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Api-Key ' . $this->speechKitApiKey,
                'X-Folder-Id: ' . $this->folderId,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return null;
        }

        return (string) $response;
    }

    private function curlPost(string $url, string $body): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Authorization: Api-Key ' . $this->speechKitApiKey,
                'X-Folder-Id: ' . $this->folderId,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return null;
        }

        return (string) $response;
    }
}
