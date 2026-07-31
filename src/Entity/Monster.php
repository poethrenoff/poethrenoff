<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Monster
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $login = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $author = '';

    #[ORM\Column(type: Types::INTEGER)]
    private int $poems = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $poemsOld = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $place = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $placeOld = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastVisitDate = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function setLogin(string $login): static
    {
        $this->login = $login;
        return $this;
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

    public function getPoems(): int
    {
        return $this->poems;
    }

    public function setPoems(int $poems): static
    {
        $this->poems = $poems;
        return $this;
    }

    public function getPoemsOld(): int
    {
        return $this->poemsOld;
    }

    public function setPoemsOld(int $poemsOld): static
    {
        $this->poemsOld = $poemsOld;
        return $this;
    }

    public function getPlace(): ?int
    {
        return $this->place;
    }

    public function setPlace(?int $place): static
    {
        $this->place = $place;
        return $this;
    }

    public function getPlaceOld(): ?int
    {
        return $this->placeOld;
    }

    public function setPlaceOld(?int $placeOld): static
    {
        $this->placeOld = $placeOld;
        return $this;
    }

    public function getLastVisitDate(): ?\DateTimeImmutable
    {
        return $this->lastVisitDate;
    }

    public function setLastVisitDate(?\DateTimeImmutable $lastVisitDate): static
    {
        $this->lastVisitDate = $lastVisitDate;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }
}
