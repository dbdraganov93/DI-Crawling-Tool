<?php

namespace App\Service;

class BrochureService
{
    private array $brochures = [];

    // Declare all properties
    private string $pdfUrl = '';
    private string $integration = '';
    private string $salesRegion = '';
    private string $brochureNumber = '';
    private string $title = '';
    private string $variety = '';
    private string $validFrom = '';
    private string $validTo = '';
    private string $visibleFrom = '';
    private array $pdfProcessingOptions = [];
    private string $layout = '';
    private array $trackingPixels = [];
    private int $companyId;

    public function __construct(int $companyId)
    {
        $this->companyId = $companyId;
    }

    public function getCompanyId(): int
    {
        return $this->companyId;
    }
    // Setters
    public function setPdfUrl(string $pdfUrl): self
    {
        $this->pdfUrl = $pdfUrl;
        return $this;
    }


    public function setIntegration(string $integration): self
    {
        $this->integration = $integration;
        return $this;
    }

    public function setSalesRegion(string $salesRegion): self
    {
        $this->salesRegion = $salesRegion;
        return $this;
    }

    public function setBrochureNumber(string $brochureNumber): self
    {
        $this->brochureNumber = $brochureNumber;
        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function setVariety(string $variety): self
    {
        $this->variety = $variety;
        return $this;
    }

    public function setValidFrom(string $validFrom): self
    {
        $this->validFrom = $validFrom;
        return $this;
    }

    public function setValidTo(string $validTo): self
    {
        $this->validTo = $validTo;
        return $this;
    }

    public function setVisibleFrom(string $visibleFrom): self
    {
        $this->visibleFrom = $visibleFrom;
        return $this;
    }

    public function setPdfProcessingOptions(array $pdfProcessingOptions): self
    {
        $this->pdfProcessingOptions = $pdfProcessingOptions;
        return $this;
    }

    public function setLayout(string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    public function setTrackingPixels(array $trackingPixels): self
    {
        $this->trackingPixels = $trackingPixels;
        return $this;
    }

    // Add current brochure to list
    public function addCurrentBrochure(): self
    {
        $this->brochures[] = [
            'pdf_url' => $this->pdfUrl,
            'integration' => $this->integration,
            'sales_region' => $this->salesRegion,
            'brochure_number' => $this->brochureNumber,
            'title' => $this->title,
            'variety' => $this->variety,
            'valid_from' => $this->validFrom,
            'valid_to' => $this->validTo,
            'visible_from' => $this->visibleFrom,
            'pdf_processing_options' => $this->pdfProcessingOptions,
            'layout' => $this->layout,
            'tracking_pixels' => $this->trackingPixels,
        ];

        return $this;
    }

    public function getBrochures(): array
    {
        return $this->brochures;
    }
}
