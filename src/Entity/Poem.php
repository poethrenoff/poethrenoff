<?php

namespace App\Entity;

use App\Enum\PoemStatus;
use App\Repository\PoemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PoemRepository::class)]
#[ORM\Index(columns: ['status', 'deleted_at'], name: 'idx_poem_status_deleted')]
#[ORM\Index(columns: ['position'], name: 'idx_poem_position')]
#[ORM\HasLifecycleCallbacks]
class Poem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['poem:list', 'poem:detail', 'poem:sidebar'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    #[Groups(['poem:list', 'poem:detail', 'poem:sidebar'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['poem:list', 'poem:detail', 'poem:sidebar'])]
    private string $content = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['poem:list', 'poem:detail', 'poem:sidebar'])]
    private ?\DateTimeImmutable $comment = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: PoemStatus::class)]
    #[Groups(['poem:list', 'poem:detail', 'poem:sidebar'])]
    private PoemStatus $status = PoemStatus::Draft;

    #[ORM\Column(type: Types::SMALLFLOAT)]
    #[Groups(['poem:list', 'poem:detail', 'poem:sidebar'])]
    private float $position = 0.0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['poem:list', 'poem:detail', 'poem:sidebar'])]
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

    private function ensureDefaults(): void
    {
        if (empty($this->title)) {
            $this->title = $this->getDefaultTitle();
        }
        if (empty($this->comment)) {
            $this->comment = $this->getDefaultComment();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDefaultTitle(): string
    {
        $lines = explode("\n", trim($this->content));
        $firstLine = trim(rtrim($lines[0] ?? '', '.,!:?;…—-'));
        return '"' . $firstLine . '..."';
    }

    public function getDisplayTitle(): string
    {
        return preg_match('/\".*\.\.\.\"$/', $this->title) ? '* * *' : mb_strtoupper($this->title);
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

    public function getDefaultComment(): ?\DateTimeImmutable
    {
        return new \DateTimeImmutable('today');
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

    public function createSnapshot(): PoemVersion
    {
        $version = new PoemVersion();
        $version->setPoem($this);
        $version->setTitle($this->title);
        $version->setContent($this->content);
        $version->setComment($this->comment);

        $this->addVersion($version);

        return $version;
    }

    public function restoreFromVersion(PoemVersion $version): static
    {
        $this->title = $version->getTitle();
        $this->content = $version->getContent();
        $this->comment = $version->getComment();

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
            && $this->comment == $poem->getComment(); // exactly like that
    }
}
