<?php

namespace App\Entity;

use App\Enum\VoteType;
use App\Repository\WorkVoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkVoteRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_work_vote', columns: ['work_id', 'ip_hash', 'user_agent_hash'])]
class WorkVote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Work::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(name: 'work_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Work $work = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $ipHash = '';

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $userAgentHash = '';

    #[ORM\Column(type: Types::STRING, length: 10, enumType: VoteType::class)]
    private VoteType $voteType = VoteType::Like;

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

    public function getWork(): ?Work
    {
        return $this->work;
    }

    public function setWork(?Work $work): static
    {
        $this->work = $work;
        return $this;
    }

    public function getIpHash(): string
    {
        return $this->ipHash;
    }

    public function setIpHash(string $ipHash): static
    {
        $this->ipHash = $ipHash;
        return $this;
    }

    public function getUserAgentHash(): string
    {
        return $this->userAgentHash;
    }

    public function setUserAgentHash(string $userAgentHash): static
    {
        $this->userAgentHash = $userAgentHash;
        return $this;
    }

    public function getVoteType(): VoteType
    {
        return $this->voteType;
    }

    public function setVoteType(VoteType $voteType): static
    {
        $this->voteType = $voteType;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->voteType->value, $this->work->getTitle());
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
}
