<?php

namespace App\Entity;

use App\Repository\BlogCommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlogCommentRepository::class)]
#[ORM\Index(columns: ['post_id', 'created_at'], name: 'idx_blog_comment_post_date')]
#[ORM\Index(columns: ['parent_id'], name: 'idx_blog_comment_parent')]
class BlogComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BlogPost::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?BlogPost $post = null;

    #[ORM\ManyToOne(targetEntity: BlogComment::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?BlogComment $parent = null;

    #[ORM\OneToMany(targetEntity: BlogComment::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $children;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $author = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $info = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    public function __construct()
    {
        $this->children = new ArrayCollection();
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

    public function getPost(): ?BlogPost
    {
        return $this->post;
    }

    public function setPost(?BlogPost $post): static
    {
        $this->post = $post;
        return $this;
    }

    public function getParent(): ?BlogComment
    {
        return $this->parent;
    }

    public function setParent(?BlogComment $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(BlogComment $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(BlogComment $child): static
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return mb_strlen($this->content) > 100
            ? mb_substr($this->content, 0, 97) . '…'
            : $this->content;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): static
    {
        $this->author = $author;
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

    public function getInfo(): ?string
    {
        return $this->info;
    }

    public function setInfo(?string $info): static
    {
        $this->info = $info;
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

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }
}
