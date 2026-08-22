<?php

namespace App\Entity;

use App\Enum\PublicationStatus;
use App\Enum\PublishPlatform;
use App\Repository\PublicationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PublicationLogRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_publication_log_poem_platform', columns: ['poem_id', 'platform'])]
#[ORM\HasLifecycleCallbacks]
class PublicationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Poem::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Poem $poem;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: PublishPlatform::class)]
    private PublishPlatform $platform;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: PublicationStatus::class)]
    private PublicationStatus $status;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $externalPostId = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $externalUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $publishedAt;

    public function __construct()
    {
        $this->publishedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->publishedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getPoem(): Poem
    {
        return $this->poem;
    }

    public function setPoem(Poem $poem): static
    {
        $this->poem = $poem;

        return $this;
    }

    public function getPlatform(): PublishPlatform
    {
        return $this->platform;
    }

    public function setPlatform(PublishPlatform $platform): static
    {
        $this->platform = $platform;

        return $this;
    }

    public function getStatus(): PublicationStatus
    {
        return $this->status;
    }

    public function setStatus(PublicationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getExternalPostId(): ?string
    {
        return $this->externalPostId;
    }

    public function setExternalPostId(?string $externalPostId): static
    {
        $this->externalPostId = $externalPostId;

        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): static
    {
        $this->externalUrl = $externalUrl;

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

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }
}
