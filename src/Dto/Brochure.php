<?php

namespace App\Dto;

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

    public static function fromArray(array $data): self
    {
        $instance = new self();

        foreach ($data as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (method_exists($instance, $method)) {
                $instance->$method($value);
            }
        }

        return $instance;
    }

    private function setPdfUrl(string $pdfUrl): void
    {
        $this->pdfUrl = $pdfUrl;
    }

    private function setIntegration(string $integration): void
    {
        $this->integration = $integration;
    }

    private function setSalesRegion(string $salesRegion): void
    {
        $this->salesRegion = $salesRegion;
    }

    private function setBrochureNumber(string $brochureNumber): void
    {
        $this->brochureNumber = $brochureNumber;
    }

    private function setTitle(string $title): void
    {
        $this->title = $title;
    }

    private function setVariety(string $variety): void
    {
        $this->variety = $variety;
    }

    private function setValidFrom(string $validFrom): void
    {
        $this->validFrom = $validFrom;
    }

    private function setValidTo(string $validTo): void
    {
        $this->validTo = $validTo;
    }

    private function setVisibleFrom(string $visibleFrom): void
    {
        $this->visibleFrom = $visibleFrom;
    }

    private function setPdfProcessingOptions(array $pdfProcessingOptions): void
    {
        $this->pdfProcessingOptions = $pdfProcessingOptions;
    }

    private function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    private function setTrackingPixels(string $trackingPixels): void
    {
        $this->trackingPixels = $trackingPixels;
    }

    private function setStoreNumber(string $storeNumber): void
    {
        $this->storeNumber = $storeNumber;
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
