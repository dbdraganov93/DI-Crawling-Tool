<?php
require_once APPLICATION_PATH . '/../vendor/autoload.php';

class Shopfully_Entity_Brochure
{
    private int $id;
    private int $retailerId;
    private string $title;
    private string $description;
    private DateTime $startDate;
    private DateTime $endDate;
    private DateTime $publishAt;
    private DateTime $unpublishAt;
    private string $thumbUrl;
    private string $publicationUrl;
    private string $notes;
    private string $type;
    private string $subType;
    private bool $isDraft;
    private bool $isPublished;
    private bool $isPremium;
    private bool $isVisible;
    private bool $isCustomTagging;
    private string $trackingUrl;
    private string $trackingUrlClient;
    private int $storeCount;
    private DateTime $created;
    private DateTime $modified;
    private bool $isActive;
    private string $pdfUrl;
    private array $images = [];
    private int $numberOfPages = 0;
    private array $stores = [];
    private array $clickouts = [];

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getRetailerId(): int
    {
        return $this->retailerId;
    }

    /**
     * @param int $retailerId
     */
    public function setRetailerId(int $retailerId): void
    {
        $this->retailerId = $retailerId;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return \DateTime
     */
    public function getStartDate(): \DateTime
    {
        return $this->startDate;
    }

    /**
     * @param \DateTime $startDate
     */
    public function setStartDate(DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }

    /**
     * @return \DateTime
     */
    public function getEndDate(): \DateTime
    {
        return $this->endDate;
    }

    /**
     * @param \DateTime $endDate
     */
    public function setEndDate(\DateTime $endDate): void
    {
        $this->endDate = $endDate;
    }

    /**
     * @return \DateTime
     */
    public function getPublishAt(): \DateTime
    {
        return $this->publishAt;
    }

    /**
     * @param \DateTime $publishAt
     */
    public function setPublishAt(\DateTime $publishAt): void
    {
        $this->publishAt = $publishAt;
    }

    /**
     * @return \DateTime
     */
    public function getUnpublishAt(): \DateTime
    {
        return $this->unpublishAt;
    }

    /**
     * @param \DateTime $unpublishAt
     */
    public function setUnpublishAt(\DateTime $unpublishAt): void
    {
        $this->unpublishAt = $unpublishAt;
    }

    /**
     * @return string
     */
    public function getThumbUrl(): string
    {
        return $this->thumbUrl;
    }

    /**
     * @param string $thumbUrl
     */
    public function setThumbUrl(string $thumbUrl): void
    {
        $this->thumbUrl = $thumbUrl;
    }

    /**
     * @return string
     */
    public function getPublicationUrl(): string
    {
        return $this->publicationUrl;
    }

    /**
     * @param string $publicationUrl
     */
    public function setPublicationUrl(string $publicationUrl): void
    {
        $this->publicationUrl = $publicationUrl;
    }

    /**
     * @return string
     */
    public function getNotes(): string
    {
        return $this->notes;
    }

    /**
     * @param string $notes
     */
    public function setNotes(string $notes): void
    {
        $this->notes = $notes;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getSubType(): string
    {
        return $this->subType;
    }

    /**
     * @param string $subType
     */
    public function setSubType(string $subType): void
    {
        $this->subType = $subType;
    }

    /**
     * @return bool
     */
    public function isDraft(): bool
    {
        return $this->isDraft;
    }

    /**
     * @param bool $isDraft
     */
    public function setIsDraft(bool $isDraft): void
    {
        $this->isDraft = $isDraft;
    }

    /**
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    /**
     * @param bool $isPublished
     */
    public function setIsPublished(bool $isPublished): void
    {
        $this->isPublished = $isPublished;
    }

    /**
     * @return bool
     */
    public function isPremium(): bool
    {
        return $this->isPremium;
    }

    /**
     * @param bool $isPremium
     */
    public function setIsPremium(bool $isPremium): void
    {
        $this->isPremium = $isPremium;
    }

    /**
     * @return bool
     */
    public function isVisible(): bool
    {
        return $this->isVisible;
    }

    /**
     * @param bool $isVisible
     */
    public function setIsVisible(bool $isVisible): void
    {
        $this->isVisible = $isVisible;
    }

    /**
     * @return bool
     */
    public function isCustomTagging(): bool
    {
        return $this->isCustomTagging;
    }

    /**
     * @param bool $isCustomTagging
     */
    public function setIsCustomTagging(bool $isCustomTagging): void
    {
        $this->isCustomTagging = $isCustomTagging;
    }

    /**
     * @return string
     */
    public function getTrackingUrl(): string
    {
        return $this->trackingUrl;
    }

    /**
     * @param string $trackingUrl
     */
    public function setTrackingUrl(string $trackingUrl): void
    {
        $this->trackingUrl = $trackingUrl;
    }

    /**
     * @return string
     */
    public function getTrackingUrlClient(): string
    {
        return $this->trackingUrlClient;
    }

    /**
     * @param string $trackingUrlClient
     */
    public function setTrackingUrlClient(string $trackingUrlClient): void
    {
        $this->trackingUrlClient = $trackingUrlClient;
    }

    /**
     * @return int
     */
    public function getStoreCount(): int
    {
        return $this->storeCount;
    }

    /**
     * @param int $storeCount
     */
    public function setStoreCount(int $storeCount): void
    {
        $this->storeCount = $storeCount;
    }

    /**
     * @return \DateTime
     */
    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    /**
     * @param \DateTime $created
     */
    public function setCreated(\DateTime $created): void
    {
        $this->created = $created;
    }

    /**
     * @return \DateTime
     */
    public function getModified(): \DateTime
    {
        return $this->modified;
    }

    /**
     * @param \DateTime $modified
     */
    public function setModified(\DateTime $modified): void
    {
        $this->modified = $modified;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @param bool $isActive
     */
    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    /**
     * @return array
     */
    public function getStores(): array
    {
        return $this->stores;
    }

    /**
     * @param array $stores
     */
    public function setStores(array $stores): void
    {
        $this->stores = $stores;
    }

    public function getPdfUrl(): string
    {
        return $this->pdfUrl;
    }

    public function setPdfUrl(string $pdfUrl): void
    {
        $this->pdfUrl = $pdfUrl;
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function setImages(array $images): void
    {
        $this->images = $images;
    }

    /**
     * @return int
     */
    public function getNumberOfPages(): int
    {
        return $this->numberOfPages;
    }

    /**
     * @param int $numberOfPages
     */
    public function setNumberOfPages(int $numberOfPages): void
    {
        $this->numberOfPages = $numberOfPages;
    }

    /**
     * @return array
     */
    public function getClickouts(): array
    {
        return $this->clickouts;
    }

    /**
     * @param array $clickouts
     */
    public function setClickouts(array $clickouts): void
    {
        $this->clickouts = $clickouts;
    }
}
