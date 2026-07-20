<?php

namespace App\Entity;

use App\Repository\PoemVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PoemVersionRepository::class)]
#[ORM\Table(name: 'poem_version')]
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
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $comment = null;

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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
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

    public function diffWith(PoemVersion $other): array
    {
        return [
            'title_changed' => $this->title !== $other->title,
            'content_changed' => $this->content !== $other->content,
            'comment_changed' => $this->comment !== $other->comment,
        ];
    }
}
