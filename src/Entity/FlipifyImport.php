<?php

namespace App\Entity;

use App\Repository\FlipifyImportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FlipifyImportRepository::class)]
#[ORM\Table(name: 'flipify_import')]
class FlipifyImport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 255)]
    private string $storedFilename;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyWebsite;

    #[ORM\Column(type: Types::JSON)]
    private array $products = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $originalFilename, string $storedFilename, ?string $companyWebsite = null, array $products = [])
    {
        $this->originalFilename = $originalFilename;
        $this->storedFilename = $storedFilename;
        $this->companyWebsite = $companyWebsite;
        $this->products = array_values($products);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getStoredFilename(): string
    {
        return $this->storedFilename;
    }

    public function getCompanyWebsite(): ?string
    {
        return $this->companyWebsite;
    }

    public function setCompanyWebsite(?string $companyWebsite): void
    {
        $this->companyWebsite = $companyWebsite;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    public function setProducts(array $products): void
    {
        $this->products = array_values($products);
    }

    public function addProduct(array $product): void
    {
        $this->products[] = $product;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getProductCount(): int
    {
        return \count($this->products);
    }
}
