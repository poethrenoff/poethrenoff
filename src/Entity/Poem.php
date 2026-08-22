<?php

namespace App\Entity;

use App\Enum\PoemStatus;
use App\Repository\PoemRepository;
use App\Trait\HasDefaultTitleTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PoemRepository::class)]
#[ORM\Index(name: 'idx_poem_status_deleted', columns: ['status', 'deleted_at'])]
#[ORM\Index(name: 'idx_poem_position', columns: ['position'])]
#[ORM\HasLifecycleCallbacks]
class Poem
{
    use HasDefaultTitleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['poem:list', 'poem:detail'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    #[Groups(['poem:list', 'poem:detail'])]
    #[Assert\Length(max: 512)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['poem:list', 'poem:detail'])]
    #[Assert\NotBlank]
    private string $content = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['poem:list', 'poem:detail'])]
    private ?\DateTimeImmutable $comment = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: PoemStatus::class)]
    #[Groups(['poem:list', 'poem:detail'])]
    private PoemStatus $status = PoemStatus::Draft;

    #[ORM\Column(type: Types::SMALLFLOAT)]
    #[Groups(['poem:list', 'poem:detail'])]
    private float $position = 0.0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['poem:list', 'poem:detail'])]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, PoemVersion> */
    #[ORM\OneToMany(
        targetEntity: PoemVersion::class,
        mappedBy: 'poem',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $versions;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->versions = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->ensureDefaults();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->ensureDefaults();
    }

    public function getBodyContent(): string
    {
        return $this->content;
    }

    public function getDefaultComment(): ?\DateTimeImmutable
    {
        return new \DateTimeImmutable('today');
    }

    public function getDisplayComment(): string
    {
        return $this->getComment()?->format('d.m.Y') ?? '';
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getComment(): ?\DateTimeImmutable
    {
        return $this->comment;
    }

    public function setComment(?\DateTimeImmutable $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function getStatus(): PoemStatus
    {
        return $this->status;
    }

    public function setStatus(PoemStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPosition(): float
    {
        return $this->position;
    }

    public function setPosition(float $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
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

    /**
     * @return Collection<int, PoemVersion>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(PoemVersion $version): static
    {
        if (!$this->versions->contains($version)) {
            $this->versions->add($version);
            $version->setPoem($this);
        }
        return $this;
    }

    public function removeVersion(PoemVersion $version): static
    {
        if ($this->versions->removeElement($version)) {
            if ($version->getPoem() === $this) {
                $version->setPoem(null);
            }
        }
        return $this;
    }

    public function trash(): static
    {
        $this->status = PoemStatus::Trash;
        $this->deletedAt = new \DateTimeImmutable();
        return $this;
    }

    public function restore(): static
    {
        $this->status = PoemStatus::Draft;
        $this->deletedAt = null;
        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }

    public function toExport(): string
    {
        $lines = [];

        $lines[] = $this->getDisplayTitle();
        $lines[] = $this->getContent();
        if ($comment = $this->getComment()) {
            $lines[] = $comment->format('d.m.Y');
        }

        return join("\n\n", $lines);
    }

    public function isEqual(Poem $poem): bool
    {
        return $this->title === $poem->getTitle()
            && $this->content === $poem->getContent()
            && ($this->comment?->format('d.m.Y') ?? '') ===
                ($poem->getComment()?->format('d.m.Y') ?? '');
    }

    public function createVersion(Poem $poem): PoemVersion
    {
        $version = new PoemVersion();
        $version->setPoem($this);
        $version->setTitle($poem->getTitle());
        $version->setContent($poem->getContent());
        $version->setComment($poem->getComment());

        return $version;
    }
}
