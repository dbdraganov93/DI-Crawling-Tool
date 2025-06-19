<?php

declare(strict_types=1);

namespace App\Entity;

class Brochure
{
    public const DEFAULT_VARIETY = 'leaflet';

    public const DEFAULT_PROCESSING_OPTIONS = [
        'version' => '2021-04-19',
        'cutPages' => true,
        'dpi' => 250,
        'maxImageSize' => 6250000,
        'allowFontSubstitution' => true,
    ];

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
    private string $trackingPixels = '';
    private string $storeNumber = '';

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

    public function setTrackingPixels(string $trackingPixels): self
    {
        $this->trackingPixels = $trackingPixels;
        return $this;
    }

    public function setStoreNumber(string $storeNumber): self
    {
        $this->storeNumber = $storeNumber;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'pdfUrl' => $this->pdfUrl,
            'integration' => $this->integration,
            'sales_region' => $this->salesRegion,
            'brochureNumber' => $this->brochureNumber,
            'title' => $this->title,
            'variety' => $this->variety !== '' ? $this->variety : self::DEFAULT_VARIETY,
            'validFrom' => $this->validFrom,
            'validTo' => $this->validTo,
            'storeNumber' => $this->storeNumber,
            'visibleFrom' => $this->visibleFrom,
            'pdfProcessingOptions' => $this->pdfProcessingOptions ?: self::DEFAULT_PROCESSING_OPTIONS,
            'trackingPixels' => $this->trackingPixels ?: '',
            'layout' => $this->layout,
        ];
    }
}
