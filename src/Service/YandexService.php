<?php

namespace App\Service;

use Aws\S3\S3Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class YandexService
{
    private ?S3Client $s3Client = null;

    public function __construct(
        #[Autowire(env: 'YANDEX_CLOUD_S3_KEY')] private string $s3Key,
        #[Autowire(env: 'YANDEX_CLOUD_S3_SECRET')] private string $s3Secret,
        #[Autowire(env: 'YANDEX_CLOUD_S3_BUCKET')] private string $s3Bucket,
        #[Autowire(env: 'YANDEX_CLOUD_S3_PREFIX')] private string $s3Prefix,
        #[Autowire(env: 'YANDEX_CLOUD_S3_REGION')] private string $s3Region,
        #[Autowire(env: 'YANDEX_CLOUD_S3_ENDPOINT')] private string $s3Endpoint,
        #[Autowire(env: 'YANDEX_CLOUD_FOLDER_ID')] private string $folderId,
        #[Autowire(env: 'YANDEX_SERVICE_API_KEY')] private string $serviceApiKey,
        private HttpClientInterface $httpClient,
    ) {
    }

    public function uploadToS3(string $localFilePath, string $fileHash): string
    {
        $s3Client = $this->getS3Client();

        $stream = fopen($localFilePath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Не удалось открыть файл для чтения');
        }

        $contentType = mime_content_type($localFilePath) ?: 'application/octet-stream';

        $s3Key = $this->buildS3Key($fileHash);
        try {
            $s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key' => $s3Key,
                'Body' => $stream,
                'ACL' => 'public-read',
                'ContentType' => $contentType,
            ]);
        } finally {
            fclose($stream);
        }

        return $s3Client->getObjectUrl($this->s3Bucket, $s3Key);
    }

    public function startRecognition(string $audioUrl): string
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
                'textNormalizationOptions' => [
                    'textNormalization' => 'TEXT_NORMALIZATION_ENABLED',
                    'literatureText' => true,
                ],
            ],
            'speakerLabelingOptions' => [
                'speakerLabeling' => 'SPEAKER_LABELING_DISABLED',
            ],
        ]);

        if ($body === false) {
            throw new \RuntimeException('Ошибка сериализации запроса распознавания');
        }

        $response = $this->request('POST', $url, $body);
        if ($response === null) {
            throw new \RuntimeException('Ошибка при запуске распознавания');
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['id'])) {
            throw new \RuntimeException('Некорректный ответ сервиса распознавания');
        }

        return (string) $data['id'];
    }

    public function checkRecognition(string $operationId): bool
    {
        $url = 'https://operation.api.cloud.yandex.net/operations/' . urlencode($operationId);
        $response = $this->request('GET', $url);
        if ($response === null) {
            throw new \RuntimeException('Ошибка при проверке статуса распознавания');
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Некорректный ответ сервиса распознавания');
        }

        if (!empty($data['done'])) {
            if (!empty($data['error'])) {
                $errorMessage = is_array($data['error'])
                    ? ($data['error']['message'] ?? 'Ошибка распознавания')
                    : (string) $data['error'];
                throw new \RuntimeException($errorMessage);
            }

            return true;
        }

        return false;
    }

    public function getRecognitionResult(string $operationId): string
    {
        $url = 'https://stt.api.cloud.yandex.net/stt/v3'
            . '/getRecognition?operation_id=' . urlencode($operationId);
        $response = $this->request('GET', $url);
        if ($response === null) {
            throw new \RuntimeException('Ошибка при получении результата распознавания');
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
            if (!empty($alternatives) && isset($alternatives[0]['text'])) {
                $text = trim((string) $alternatives[0]['text']);
                if ($text !== '') {
                    $texts[] = mb_ucfirst($text);
                }
            }
        }

        if (empty($texts)) {
            throw new \RuntimeException('Пустой результат распознавания');
        }

        return implode("\n", $texts);
    }

    public function formatPoem(string $text): string
    {
        $url = 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion';

        $body = json_encode([
            'modelUri' => 'gpt://' . $this->folderId . '/yandexgpt-lite/latest',
            'completionOptions' => [
                'stream' => false,
                'temperature' => 0.3,
                'maxTokens' => "2000",
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'text' => <<<PROMPT
Ты — помощник поэта. На вход ты получишь текст, который может быть уже частично разделен на строки или
идти одной строкой. Твоя задача — расставить знаки препинания и разбить текст на строки и строфы так,
чтобы получилось красивое стихотворение. Не меняй слова, только пунктуацию и переносы строк.'
PROMPT,
                ],
                [
                    'role' => 'user',
                    'text' => $text,
                ],
            ],
        ]);

        if ($body === false) {
            return $text;
        }

        $response = $this->request('POST', $url, $body);
        if ($response === null) {
            return $text;
        }

        $data = json_decode($response, true);
        $resultText = $data['result']['alternatives'][0]['message']['text'] ?? null;

        return $resultText ? trim((string) $resultText) : $text;
    }

    public function cleanupS3(string $fileHash): void
    {
        $s3Client = $this->getS3Client();

        $s3Key = $this->buildS3Key($fileHash);
        try {
            $s3Client->deleteObject([
                'Bucket' => $this->s3Bucket,
                'Key' => $s3Key,
            ]);
        } catch (\Throwable $e) {
        }
    }

    private function getS3Client(): S3Client
    {
        return $this->s3Client ??= new S3Client([
            'version' => 'latest',
            'region' => $this->s3Region,
            'endpoint' => $this->s3Endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $this->s3Key,
                'secret' => $this->s3Secret,
            ],
        ]);
    }

    private function buildS3Key(string $fileHash): string
    {
        return trim($this->s3Prefix, '/') . '/' . $fileHash;
    }

    /**
     * @param string $method
     * @param string $url
     * @param string|null $body
     * @return string|null
     */
    private function request(string $method, string $url, ?string $body = null): ?string
    {
        $options = [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Api-Key ' . $this->serviceApiKey,
                'X-Folder-Id' => $this->folderId,
                'Accept' => 'application/json',
            ],
        ];
        if ($body !== null) {
            $options['headers']['Content-Type'] = 'application/json';
            $options['body'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);

            if ($response->getStatusCode() >= 400) {
                return null;
            }

            return $response->getContent();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
