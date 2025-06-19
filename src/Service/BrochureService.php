<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Brochure;

class BrochureService
{
    private int $companyId;
    private array $brochures = [];
    private Brochure $currentBrochure;

    public function __construct(int $companyId)
    {
        $this->companyId = $companyId;
        $this->currentBrochure = new Brochure();
        $this->currentBrochure->setIntegration('https://iproto.offerista.com/api/integrations/' . $companyId);
    }

    public function getCompanyId(): int
    {
        return $this->companyId;
    }

    // Setters delegate to the current brochure instance
    public function setPdfUrl(string $pdfUrl): self
    {
        $this->currentBrochure->setPdfUrl($pdfUrl);
        return $this;
    }

    public function setStoreNumber(string $storeNumber): self
    {
        $this->currentBrochure->setStoreNumber($storeNumber);
        return $this;
    }

    public function setIntegration(int $integration): self
    {
        $this->currentBrochure->setIntegration('https://iproto.offerista.com/api/integrations/' . $integration);
        return $this;
    }

    public function setSalesRegion(string $salesRegion): self
    {
        $this->currentBrochure->setSalesRegion($salesRegion);
        return $this;
    }

    public function setBrochureNumber(string $brochureNumber): self
    {
        $this->currentBrochure->setBrochureNumber($brochureNumber);
        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->currentBrochure->setTitle($title);
        return $this;
    }

    public function setVariety(string $variety): self
    {
        $this->currentBrochure->setVariety($variety);
        return $this;
    }

    public function setValidFrom(string $validFrom): self
    {
        $this->currentBrochure->setValidFrom($validFrom);
        return $this;
    }

    public function setValidTo(string $validTo): self
    {
        $this->currentBrochure->setValidTo($validTo);
        return $this;
    }

    public function setVisibleFrom(string $visibleFrom): self
    {
        $this->currentBrochure->setVisibleFrom($visibleFrom);
        return $this;
    }

    public function setPdfProcessingOptions(array $pdfProcessingOptions): self
    {
        $this->currentBrochure->setPdfProcessingOptions($pdfProcessingOptions);
        return $this;
    }

    public function setLayout(string $layout): self
    {
        $this->currentBrochure->setLayout($layout);
        return $this;
    }

    public function setTrackingPixels(string $trackingPixels): self
    {
        $this->currentBrochure->setTrackingPixels($trackingPixels);
        return $this;
    }

    // Add current brochure to list
    public function addCurrentBrochure(): self
    {
        $this->brochures[] = $this->currentBrochure;
        $this->currentBrochure = new Brochure();
        $this->currentBrochure->setIntegration('https://iproto.offerista.com/api/integrations/' . $this->companyId);

        return $this;
    }

    public function getBrochures(): array
    {
        return array_map(static fn (Brochure $b) => $b->toArray(), $this->brochures);
    }
}
