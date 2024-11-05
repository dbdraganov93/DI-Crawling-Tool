<?php

namespace App\Entity;

use App\Repository\CrawlerRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User; // Make sure to import the User entity

#[ORM\Entity(repositoryClass: CrawlerRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Crawler
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $type = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $source = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $cron = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $behaviour = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $status = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $script = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $companyId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $created = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updated = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getCron(): ?string
    {
        return $this->cron;
    }

    public function setCron(?string $cron): static
    {
        $this->cron = $cron;

        return $this;
    }

    public function getBehaviour(): ?string
    {
        return $this->behaviour;
    }

    public function setBehaviour(string $behaviour): static
    {
        $this->behaviour = $behaviour;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getScript(): ?string
    {
        return $this->script;
    }

    public function setScript(string $script): static
    {
        $this->script = $script;

        return $this;
    }

    public function getCompanyId(): ?Company
    {
        return $this->companyId;
    }

    public function setCompanyId(?Company $company): self
    {
        $this->companyId = $company;
        return $this;
    }


    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getCreated(): ?\DateTimeImmutable
    {
        return $this->created;
    }

    #[ORM\PrePersist]
    public function setCreatedValue(): void
    {
        $this->created = new \DateTimeImmutable();
        $this->updated = new \DateTimeImmutable(); // Set updated as well on initial creation

    }

    public function getUpdated(): ?\DateTimeImmutable
    {
        return $this->updated;
    }


    #[ORM\PreUpdate]
    public function setUpdatedValue(): void
    {
        $this->updated = new \DateTimeImmutable();
    }
}
