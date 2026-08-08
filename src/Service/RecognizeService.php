<?php

namespace App\Service;

use App\Entity\Audio;
use App\Entity\RecognizeTask;
use App\Enum\RecognizeTaskStatus;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;

class RecognizeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private YandexService $yandexService,
        private FileUploadService $fileUploadService,
    ) {
    }

    /**
     * @throws RandomException
     */
    public function createTask(Audio $audio): RecognizeTask
    {
        $task = new RecognizeTask()
            ->setId(bin2hex(random_bytes(16)))
            ->setAudio($audio);
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    public function advanceTask(RecognizeTask $task): RecognizeTaskStatus
    {
        $status = $task->getStatus();

        if ($status === RecognizeTaskStatus::Completed || $status === RecognizeTaskStatus::Error) {
            return $status;
        }

        return match ($status) {
            RecognizeTaskStatus::Pending => $this->stepUpload($task),
            RecognizeTaskStatus::Uploaded => $this->stepStartRecognition($task),
            RecognizeTaskStatus::Recognizing => $this->stepCheckRecognition($task),
            RecognizeTaskStatus::Recognized => $this->stepGetResult($task),
            RecognizeTaskStatus::Formatting => $this->stepFormat($task),
        };
    }

    private function stepUpload(RecognizeTask $task): RecognizeTaskStatus
    {
        $fileName = basename($task->getAudio()->getFilePath());
        $localPath = $this->fileUploadService->getAudioDir() . '/' . $fileName;

        if (!file_exists($localPath)) {
            $task->setStatus(RecognizeTaskStatus::Error)
                ->setErrorMessage('Аудиофайл не найден');
            $this->entityManager->flush();

            return RecognizeTaskStatus::Error;
        }

        try {
            $audioUrl = $this->yandexService->uploadToS3($localPath, $task->getId());
            $task->setStatus(RecognizeTaskStatus::Uploaded)
                ->setStepData(['audioUrl' => $audioUrl]);
            $this->entityManager->flush();

            return RecognizeTaskStatus::Uploaded;
        } catch (\RuntimeException $e) {
            $this->yandexService->cleanupS3($task->getId());

            $task->setStatus(RecognizeTaskStatus::Error)
                ->setErrorMessage($e->getMessage());
            $this->entityManager->flush();

            return RecognizeTaskStatus::Error;
        }
    }

    private function stepStartRecognition(RecognizeTask $task): RecognizeTaskStatus
    {
        $stepData = $task->getStepData();
        $audioUrl = $stepData['audioUrl'] ?? null;
        if ($audioUrl === null) {
            $task->setStatus(RecognizeTaskStatus::Error)
                ->setErrorMessage('Отсутствует URL аудиофайла');
            $this->entityManager->flush();

            return RecognizeTaskStatus::Error;
        }

        try {
            $operationId = $this->yandexService->startRecognition($audioUrl);
            $task->setStatus(RecognizeTaskStatus::Recognizing)
                ->setStepData(['operationId' => $operationId]);
            $this->entityManager->flush();

            return RecognizeTaskStatus::Recognizing;
        } catch (\RuntimeException $e) {
            $this->yandexService->cleanupS3($task->getId());

            $task->setStatus(RecognizeTaskStatus::Error)
                ->setErrorMessage($e->getMessage());
            $this->entityManager->flush();

            return RecognizeTaskStatus::Error;
        }
    }

    private function stepCheckRecognition(RecognizeTask $task): RecognizeTaskStatus
    {
        $stepData = $task->getStepData();
        $operationId = $stepData['operationId'] ?? null;
        if ($operationId === null) {
            $task->setStatus(RecognizeTaskStatus::Error)
                ->setErrorMessage('Отсутствует operationId');
            $this->entityManager->flush();

            return RecognizeTaskStatus::Error;
        }

        try {
            if ($this->yandexService->checkRecognition($operationId)) {
                $task->setStatus(RecognizeTaskStatus::Recognized);
                $this->entityManager->flush();

                return RecognizeTaskStatus::Recognized;
            }

            return RecognizeTaskStatus::Recognizing;
        } catch (\RuntimeException $e) {
            $this->yandexService->cleanupS3($task->getId());

            $task->setStatus(RecognizeTaskStatus::Error)
                ->setErrorMessage($e->getMessage());
            $this->entityManager->flush();

            return RecognizeTaskStatus::Error;
        }
    }

    private function stepGetResult(RecognizeTask $task): RecognizeTaskStatus
    {
        $stepData = $task->getStepData();
        $operationId = $stepData['operationId'] ?? null;
        if ($operationId === null) {
            $task->setStatus(RecognizeTaskStatus::Error)
                ->setErrorMessage('Отсутствует operationId');
            $this->entityManager->flush();

            return RecognizeTaskStatus::Error;
        }

        try {
            $text = $this->yandexService->getRecognitionResult($operationId);
            $task->setStatus(RecognizeTaskStatus::Formatting)
                ->setStepData(['rawText' => $text]);
            $this->entityManager->flush();

            return RecognizeTaskStatus::Formatting;
        } catch (\RuntimeException $e) {
            $this->yandexService->cleanupS3($task->getId());

            $task->setStatus(RecognizeTaskStatus::Error)
                ->setErrorMessage($e->getMessage());
            $this->entityManager->flush();

            return RecognizeTaskStatus::Error;
        }
    }

    private function stepFormat(RecognizeTask $task): RecognizeTaskStatus
    {
        $stepData = $task->getStepData();
        $rawText = $stepData['rawText'] ?? null;
        if ($rawText === null) {
            $task->setStatus(RecognizeTaskStatus::Error)
                ->setErrorMessage('Отсутствует распознанный текст');
            $this->entityManager->flush();

            return RecognizeTaskStatus::Error;
        }

        try {
            $formatted = $this->yandexService->formatPoem($rawText);

            $task->setStatus(RecognizeTaskStatus::Completed)
                ->setResultText($formatted);
            $this->entityManager->flush();

            return RecognizeTaskStatus::Completed;
        } catch (\RuntimeException $e) {
            $task->setStatus(RecognizeTaskStatus::Error)
                ->setErrorMessage($e->getMessage());
            $this->entityManager->flush();

            return RecognizeTaskStatus::Error;
        } finally {
            $this->yandexService->cleanupS3($task->getId());
        }
    }
}
