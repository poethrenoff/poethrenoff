<?php

namespace App\Entity;

use App\Entity\Poem;
use App\Repository\PoemVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PoemVersionRepository::class)]
#[ORM\Index(columns: ['poem_id', 'created_at'], name: 'idx_poem_version_poem_date')]
class PoemVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Poem::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(name: 'poem_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Poem $poem = null;

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    #[Assert\Length(max: 512)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $content = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $comment = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getPoem(): ?Poem
    {
        return $this->poem;
    }

    public function setPoem(?Poem $poem): static
    {
        $this->poem = $poem;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }

    /**
     * @return array{title_changed: bool, content_changed: bool, comment_changed: bool}
     */
    public function diffWithVersion(PoemVersion $version): array
    {
        return [
            'title_changed' => $this->title !== $version->title,
            'content_changed' => $this->content !== $version->content,
            'comment_changed' => ($this->comment?->format('d.m.Y') ?? '') !==
                ($version->comment?->format('d.m.Y') ?? ''),
        ];
    }

    /**
     * @return array{title_changed: bool, content_changed: bool, comment_changed: bool}
     */
    public function diffWithPoem(Poem $poem): array
    {
        return [
            'title_changed' => $this->title !== $poem->getTitle(),
            'content_changed' => $this->content !== $poem->getContent(),
            'comment_changed' => ($this->comment?->format('d.m.Y') ?? '') !==
                ($poem->getComment()?->format('d.m.Y') ?? ''),
        ];
    }

    /**
     * Virtual property for Sonata Admin diff view
     */
    public function getDiff(): ?string
    {
        return null;
    }
}
