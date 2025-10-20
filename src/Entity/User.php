<?php
// src/Entity/User.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: "App\Repository\UserRepository")]
class User implements UserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type:"integer")]
    private $id;

    #[ORM\Column(type:"string", unique:true)]
    private $email; // Required for Symfony UserInterface

    #[ORM\Column(type:"json")]
    private $roles = [];

    // Fields from 42 API
    #[ORM\Column(type:"string", length:255, nullable:true)]
    private $intraLogin;

    #[ORM\Column(type:"string", length:255, nullable:true)]
    private $usualFullName;

    #[ORM\Column(type:"string", length:255, nullable:true)]
    private $displayName;

    #[ORM\Column(type:"string", length:255, nullable:true)]
    private $kind;

    #[ORM\Column(type:"string", length:255, nullable:true)]
    private $image;

    #[ORM\Column(type:"string", length:255, nullable:true)]
    private $location;

    #[ORM\Column(type:"json", nullable:true)]
    private $projects = [];

    #[ORM\Column(type:"json", nullable:true)]
    private $campus = [];

    // Getters & Setters

    public function getId(): ?int { return $this->id; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }
    public function getRoles(): array { return $this->roles; }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    public function getIntraLogin(): ?string { return $this->intraLogin; }
    public function setIntraLogin(?string $intraLogin): self { $this->intraLogin = $intraLogin; return $this; }

    public function getUsualFullName(): ?string { return $this->usualFullName; }
    public function setUsualFullName(?string $name): self { $this->usualFullName = $name; return $this; }

    public function getDisplayName(): ?string { return $this->displayName; }
    public function setDisplayName(?string $name): self { $this->displayName = $name; return $this; }

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

    // Symfony UserInterface methods
    public function getPassword(): ?string { return null; } // OAuth only
    public function getUserIdentifier(): string { return $this->email; }
    public function eraseCredentials(): void {}
}
