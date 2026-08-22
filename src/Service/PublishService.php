<?php

namespace App\Service;

use App\Entity\Poem;
use App\Entity\PublicationLog;
use App\Enum\PublicationStatus;
use App\Enum\PublishPlatform;
use App\Repository\PublicationLogRepository;
use App\Service\Publishing\PublicationResult;
use App\Service\Publishing\SocialPlatformRegistry;
use Doctrine\ORM\EntityManagerInterface;

class PublishService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SocialPlatformRegistry $registry,
        private PublicationLogRepository $logRepository,
    ) {
    }

    public function publish(Poem $poem, PublishPlatform $platform): PublicationLog
    {
        $result = $this->registry->get($platform)->publish($poem);

        if ($result->isSuccess()) {
            return $this->recordSuccess($poem, $platform, $result);
        }

        return $this->recordError($poem, $platform, $result->getErrorMessage() ?? 'Ошибка публикации');
    }

    public function hasSuccess(Poem $poem, PublishPlatform $platform): bool
    {
        $log = $this->logRepository->findOneBy(['poem' => $poem, 'platform' => $platform]);

        return $log !== null && $log->getStatus() === PublicationStatus::Success;
    }

    private function recordSuccess(Poem $poem, PublishPlatform $platform, PublicationResult $result): PublicationLog
    {
        $log = $this->findOrCreateLog($poem, $platform);

        $log->setStatus(PublicationStatus::Success)
            ->setExternalPostId($result->getExternalPostId())
            ->setExternalUrl($result->getExternalUrl())
            ->setErrorMessage(null)
            ->setPublishedAt(new \DateTimeImmutable());

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    private function recordError(Poem $poem, PublishPlatform $platform, string $message): PublicationLog
    {
        $log = $this->findOrCreateLog($poem, $platform);

        $log->setStatus(PublicationStatus::Error)
            ->setExternalPostId(null)
            ->setExternalUrl(null)
            ->setErrorMessage($message)
            ->setPublishedAt(new \DateTimeImmutable());

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    private function findOrCreateLog(Poem $poem, PublishPlatform $platform): PublicationLog
    {
        $log = $this->logRepository->findOneBy([
            'poem' => $poem,
            'platform' => $platform,
        ]);

        if ($log === null) {
            $log = new PublicationLog();
            $log->setPoem($poem);
            $log->setPlatform($platform);
        }

        return $log;
    }
}
