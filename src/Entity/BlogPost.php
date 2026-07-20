<?php

namespace App\Entity;

use App\Repository\BlogPostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlogPostRepository::class)]
#[ORM\Index(columns: ['published_at'], name: 'idx_blog_post_date')]
#[ORM\Index(columns: ['is_active'], name: 'idx_blog_post_active')]
class BlogPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $publishedAt;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'posts')]
    #[ORM\JoinTable(name: 'tag_blog_post')]
    private Collection $tags;

    #[ORM\OneToMany(targetEntity: BlogComment::class, mappedBy: 'post', cascade: ['remove'])]
    private Collection $comments;

    public function __construct()
    {
        $this->publishedAt = new \DateTimeImmutable();
        $this->tags = new ArrayCollection();
        $this->comments = new ArrayCollection();
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

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
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

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function __toString(): string
    {
        return mb_strlen($this->content) > 100
            ? mb_substr($this->content, 0, 97) . '…'
            : $this->content;
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(BlogComment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setPost($this);
        }
        return $this;
    }

    public function removeComment(BlogComment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getPost() === $this) {
                $comment->setPost(null);
            }
        }
        return $this;
    }
}
