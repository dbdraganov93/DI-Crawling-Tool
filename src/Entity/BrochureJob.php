<?php

namespace App\Entity;

use App\Repository\BrochureJobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BrochureJobRepository::class)]
class BrochureJob
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column(length: 255)]
    private string $pdfPath = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $searchWebsite = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resultPdf = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resultJson = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getPdfPath(): string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(string $pdfPath): self
    {
        $this->pdfPath = $pdfPath;
        return $this;
    }

    public function getResultPdf(): ?string
    {
        return $this->resultPdf;
    }

    public function setResultPdf(?string $resultPdf): self
    {
        $this->resultPdf = $resultPdf;
        return $this;
    }

    public function getResultJson(): ?string
    {
        return $this->resultJson;
    }

    public function setResultJson(?string $resultJson): self
    {
        $this->resultJson = $resultJson;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getSearchWebsite(): ?string
    {
        return $this->searchWebsite;
    }

    public function setSearchWebsite(?string $searchWebsite): self
    {
        $this->searchWebsite = $searchWebsite;
        return $this;
    }
}
