<?php

namespace App\Entity;

use App\Repository\BlogCommentRepository;
use App\Trait\CommentFieldsTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BlogCommentRepository::class)]
#[ORM\Index(columns: ['post_id', 'created_at'], name: 'idx_blog_comment_post_date')]
#[ORM\Index(columns: ['parent_id'], name: 'idx_blog_comment_parent')]
class BlogComment
{
    use CommentFieldsTrait;

    #[ORM\ManyToOne(targetEntity: BlogPost::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?BlogPost $post = null;

    #[ORM\ManyToOne(targetEntity: BlogComment::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?BlogComment $parent = null;

    /** @var Collection<int, BlogComment> */
    #[ORM\OneToMany(targetEntity: BlogComment::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    /**
     * @return Collection<int, BlogComment>
     */
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
}
