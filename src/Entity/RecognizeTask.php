<?php

namespace App\Entity;

use App\Enum\RecognizeTaskStatus;
use App\Repository\RecognizeTaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecognizeTaskRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RecognizeTask
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Audio::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Audio $audio;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: RecognizeTaskStatus::class)]
    private RecognizeTaskStatus $status;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $resultText = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $stepData = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->status = RecognizeTaskStatus::Pending;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getAudio(): Audio
    {
        return $this->audio;
    }

    public function setAudio(Audio $audio): static
    {
        $this->audio = $audio;

        return $this;
    }

    public function getStatus(): RecognizeTaskStatus
    {
        return $this->status;
    }

    public function setStatus(RecognizeTaskStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getResultText(): ?string
    {
        return $this->resultText;
    }

    public function setResultText(?string $resultText): static
    {
        $this->resultText = $resultText;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStepData(): ?array
    {
        return $this->stepData;
    }

    /**
     * @param array<string, mixed>|null $stepData
     */
    public function setStepData(?array $stepData): static
    {
        $this->stepData = $stepData;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
