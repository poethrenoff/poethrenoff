<?php

namespace App\Entity;

use App\Repository\WorkCommentRepository;
use App\Trait\CommentFieldsTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WorkCommentRepository::class)]
#[ORM\Index(name: 'idx_work_comment_work_date', columns: ['work_id', 'created_at'])]
#[ORM\Index(name: 'idx_work_comment_parent', columns: ['parent_id'])]
class WorkComment
{
    use CommentFieldsTrait;

    #[ORM\ManyToOne(targetEntity: Work::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(name: 'work_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Work $work = null;

    #[ORM\ManyToOne(targetEntity: WorkComment::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?WorkComment $parent = null;

    /** @var Collection<int, WorkComment> */
    #[ORM\OneToMany(targetEntity: WorkComment::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getWork(): ?Work
    {
        return $this->work;
    }

    public function setWork(?Work $work): static
    {
        $this->work = $work;
        return $this;
    }

    public function getParent(): ?WorkComment
    {
        return $this->parent;
    }

    public function setParent(?WorkComment $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Collection<int, WorkComment>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(WorkComment $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(WorkComment $child): static
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }
}
