<?php

namespace App\Entity;

use App\Repository\FlipifyImportRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FlipifyImportRepository::class)]
#[ORM\Table(name: 'flipify_import')]
class FlipifyImport
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

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

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $processedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $processingStartedAt = null;

    public function __construct(string $originalFilename, string $storedFilename, ?string $companyWebsite = null, array $products = [])
    {
        $this->originalFilename = $originalFilename;
        $this->storedFilename = $storedFilename;
        $this->companyWebsite = $companyWebsite;
        $this->products = array_values($products);
        $this->createdAt = new DateTimeImmutable();
        $this->status = $products === [] ? self::STATUS_PENDING : self::STATUS_COMPLETED;
        if ($products !== []) {
            $this->processedAt = $this->createdAt;
        }
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getProcessedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function markProcessing(?DateTimeImmutable $startedAt = null): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->errorMessage = null;
        $this->processedAt = null;
        $this->products = [];
        $this->processingStartedAt = $startedAt ?? new DateTimeImmutable();
    }

    /**
     * @param array<int, array<string, mixed>> $products
     */
    public function markCompleted(array $products): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->setProducts($products);
        $this->processedAt = new DateTimeImmutable();
        $this->errorMessage = null;
        $this->processingStartedAt = null;
    }

    public function markFailed(string $errorMessage): void
    {
        $this->status = self::STATUS_FAILED;
        $this->products = [];
        $this->processedAt = new DateTimeImmutable();
        $this->errorMessage = function_exists('mb_substr')
            ? mb_substr($errorMessage, 0, 1000)
            : substr($errorMessage, 0, 1000);
        $this->processingStartedAt = null;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            default => ucfirst($this->status),
        };
    }

    public function getProductCount(): int
    {
        return \count($this->products);
    }

    public function getProcessingStartedAt(): ?DateTimeImmutable
    {
        return $this->processingStartedAt;
    }

    public function hasProcessingTimedOut(int $timeoutSeconds, ?DateTimeImmutable $now = null): bool
    {
        if (!$this->isProcessing() || $this->processingStartedAt === null) {
            return false;
        }

        $timeoutSeconds = max(1, $timeoutSeconds);
        $now ??= new DateTimeImmutable();

        return $this->processingStartedAt->getTimestamp() <= ($now->getTimestamp() - $timeoutSeconds);
    }
}
