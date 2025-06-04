<?php

namespace App\Entity;

use App\Repository\ShopfullyLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShopfullyLogRepository::class)]
class ShopfullyLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 255)]
    private ?string $companyName = null;

    #[ORM\Column]
    private ?int $iprotoId = null;

    #[ORM\Column(length: 255)]
    private ?string $locale = null;

    #[ORM\Column]
    private array $data = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $status = null;



    #[ORM\Column]
    private int $noticesCount = 0;

    #[ORM\Column]
    private int $warningsCount = 0;

    #[ORM\Column]
    private int $errorsCount = 0;

    #[ORM\Column(length: 255)]
    private string $importType;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $importId = null;

    public function getImportId(): ?int
    {
        return $this->importId;
    }

    public function setImportId(?int $importId): static
    {
        $this->importId = $importId;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getIprotoId(): ?int
    {
        return $this->iprotoId;
    }

    public function setIprotoId(int $iprotoId): static
    {
        $this->iprotoId = $iprotoId;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function getNoticesCount(): int
    {
        return $this->noticesCount;
    }

    public function setNoticesCount(int $noticesCount): static
    {
        $this->noticesCount = $noticesCount;
        return $this;
    }

    public function getWarningsCount(): int
    {
        return $this->warningsCount;
    }

    public function setWarningsCount(int $warningsCount): static
    {
        $this->warningsCount = $warningsCount;
        return $this;
    }

    public function getErrorsCount(): int
    {
        return $this->errorsCount;
    }

    public function setErrorsCount(int $errorsCount): static
    {
        $this->errorsCount = $errorsCount;
        return $this;
    }

    public function getImportType(): string
    {
        return $this->importType;
    }

    public function setImportType(string $importType): static
    {
        $this->importType = $importType;
        return $this;
    }
}
