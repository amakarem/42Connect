<?php

namespace App\Entity;

use App\Repository\VibeRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: VibeRepository::class)]
#[ORM\Table(name: "vibes")]
class Vibe
{
    #[ORM\Id]
    #[ORM\Column(type: "string", length: 255)]
    private ?string $uid = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "vibes")]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", onDelete: "CASCADE")]
    private ?User $user = null;

    #[ORM\Column(type: "text")]
    private ?string $originalVibe = null;

    #[ORM\Column(type: "text")]
    private ?string $vibe = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $embeddingModel = null;

    #[ORM\Column(type: "datetime_immutable")]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: "datetime_immutable")]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters & setters
    public function getUid(): ?string { return $this->uid; }
    public function setUid(string $uid): static { $this->uid = $uid; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getOriginalVibe(): ?string { return $this->originalVibe; }
    public function setOriginalVibe(string $originalVibe): static { $this->originalVibe = $originalVibe; return $this; }

    public function getVibe(): ?string { return $this->vibe; }
    public function setVibe(string $vibe): static { $this->vibe = $vibe; return $this; }

    public function getEmbeddingModel(): ?string { return $this->embeddingModel; }
    public function setEmbeddingModel(string $embeddingModel): static { $this->embeddingModel = $embeddingModel; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
