<?php

namespace App\Entity;

use App\Repository\WorkRepository;
use App\Trait\HasDefaultTitleTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WorkRepository::class)]
#[ORM\Index(columns: ['group_id', 'position'], name: 'idx_work_group_position')]
#[ORM\Index(columns: ['is_active'], name: 'idx_work_active')]
class Work
{
    use HasDefaultTitleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WorkGroup::class)]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?WorkGroup $group = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $text = '';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::SMALLFLOAT)]
    private float $position = 0.0;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::INTEGER)]
    private int $likesCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $dislikesCount = 0;

    #[ORM\OneToMany(targetEntity: WorkComment::class, mappedBy: 'work', cascade: ['remove'])]
    private Collection $comments;

    #[ORM\OneToMany(targetEntity: WorkVote::class, mappedBy: 'work', cascade: ['remove'])]
    private Collection $votes;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->votes = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->ensureDefaults();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->ensureDefaults();
    }

    protected function getBodyContent(): string
    {
        return $this->text;
    }

    protected function getDefaultComment(): string
    {
        return (new \DateTimeImmutable())->format('d.m.Y');
    }

    public function getDisplayTitle(): string
    {
        return preg_match('/\".*\.\.\.\"$/', $this->title) ? '* * *' : $this->title;
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

    public function __toString(): string
    {
        return $this->title;
    }

    public function getGroup(): ?WorkGroup
    {
        return $this->group;
    }

    public function setGroup(?WorkGroup $group): static
    {
        $this->group = $group;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getText(): string
    {
        return rtrim($this->text);
    }

    public function setText(string $text): static
    {
        $this->text = $text;
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

    public function getPosition(): float
    {
        return $this->position;
    }

    public function setPosition(float $position): static
    {
        $this->position = $position;
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

    public function getLikesCount(): int
    {
        return $this->likesCount;
    }

    public function setLikesCount(int $likesCount): static
    {
        $this->likesCount = $likesCount;
        return $this;
    }

    public function getDislikesCount(): int
    {
        return $this->dislikesCount;
    }

    public function setDislikesCount(int $dislikesCount): static
    {
        $this->dislikesCount = $dislikesCount;
        return $this;
    }

    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(WorkComment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setWork($this);
        }
        return $this;
    }

    public function removeComment(WorkComment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getWork() === $this) {
                $comment->setWork(null);
            }
        }
        return $this;
    }

    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(WorkVote $vote): static
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setWork($this);
        }
        return $this;
    }

    public function removeVote(WorkVote $vote): static
    {
        if ($this->votes->removeElement($vote)) {
            if ($vote->getWork() === $this) {
                $vote->setWork(null);
            }
        }
        return $this;
    }
}
