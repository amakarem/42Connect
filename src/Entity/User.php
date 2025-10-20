<?php
// src/Entity/User.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use App\Entity\Vibe;

#[ORM\Entity(repositoryClass: "App\Repository\UserRepository")]
#[ORM\Table(name: '"user"')] // PostgreSQL reserved word, must be quoted
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: "json")]
    private array $roles = [];

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $intraLogin = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $usualFullName = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $kind = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $projects = [];

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $campus = [];

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToOne(mappedBy: "user", targetEntity: Vibe::class, cascade: ["persist", "remove"])]
    private ?Vibe $vibe = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // -------------------------
    // Getters / Setters
    // -------------------------
    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array { return array_unique(array_merge($this->roles, ['ROLE_USER'])); }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $password): self { $this->password = $password; return $this; }

    public function eraseCredentials(): void {}

    public function getIntraLogin(): ?string { return $this->intraLogin; }
    public function setIntraLogin(?string $intraLogin): self { $this->intraLogin = $intraLogin; return $this; }

    public function getUsualFullName(): ?string { return $this->usualFullName; }
    public function setUsualFullName(?string $usualFullName): self { $this->usualFullName = $usualFullName; return $this; }

    public function getDisplayName(): ?string { return $this->displayName; }
    public function setDisplayName(?string $displayName): self { $this->displayName = $displayName; return $this; }

    public function getKind(): ?string { return $this->kind; }
    public function setKind(?string $kind): self { $this->kind = $kind; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): self { $this->image = $image; return $this; }

    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): self { $this->location = $location; return $this; }

    public function getProjects(): ?array { return $this->projects; }
    public function setProjects(?array $projects): self { $this->projects = $projects; return $this; }

    public function getCampus(): ?array { return $this->campus; }
    public function setCampus(?array $campus): self { $this->campus = $campus; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    // -------------------------
    // Vibe relationship
    // -------------------------
    public function getVibe(): ?Vibe { return $this->vibe; }

    public function setVibe(?Vibe $vibe): self
    {
        $this->vibe = $vibe;
        if ($vibe && $vibe->getUser() !== $this) {
            $vibe->setUser($this);
        }
        return $this;
    }
}
