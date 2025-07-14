<?php

namespace App\Dto;

class Brochure
{
    private const DEFAULT_VARIETY = 'leaflet';
    private const DEFAULT_TYPE = 'default';
    private const INTEGRATION_URL = 'https://iproto.offerista.com/api/integrations/';
    private const DEFAULT_PROCESSING_OPTIONS = [
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
    private string $variety = self::DEFAULT_VARIETY;
    private string $validFrom = '';
    private string $validTo = '';
    private string $visibleFrom = '';
    private array $pdfProcessingOptions = [];
    private string $layout = '';
    private string $trackingPixels = '';
    private string $storeNumber = '';
    private string $zipcode = '';
    private string $type = self::DEFAULT_TYPE;

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

    public function getPdfUrl(): string
    {
        return $this->pdfUrl;
    }

    private function setIntegration(string $integration): void
    {
        $this->integration = self::INTEGRATION_URL . $integration;
    }

    public function getIntegration(): string
    {
        return $this->integration;
    }

    private function setSalesRegion(string $salesRegion): void
    {
        $this->salesRegion = $salesRegion;
    }

    public function getSalesRegion(): string
    {
        return $this->salesRegion;
    }

    private function setBrochureNumber(string $brochureNumber): void
    {
        $this->brochureNumber = $brochureNumber;
    }

    public function getBrochureNumber(): string
    {
        return $this->brochureNumber;
    }

    private function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    private function setVariety(string $variety): void
    {
        $this->variety = $variety;
    }

    public function getVariety(): string
    {
        return $this->variety;
    }

    private function setValidFrom(string $validFrom): void
    {
        $this->validFrom = $validFrom;
    }

    public function getValidFrom(): string
    {
        return $this->validFrom;
    }

    private function setValidTo(string $validTo): void
    {
        $this->validTo = $validTo;
    }

    public function getValidTo(): string
    {
        return $this->validTo;
    }

    private function setVisibleFrom(string $visibleFrom): void
    {
        $this->visibleFrom = $visibleFrom;
    }

    public function getVisibleFrom(): string
    {
        return $this->visibleFrom;
    }

    private function setPdfProcessingOptions(array $pdfProcessingOptions): void
    {
        $this->pdfProcessingOptions = $pdfProcessingOptions;
    }

    public function getPdfProcessingOptions(): array
    {
        return $this->pdfProcessingOptions = [];
    }

    private function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    private function setTrackingPixels(string $trackingPixels): void
    {
        $this->trackingPixels = $trackingPixels;
    }

    public function getTrackingPixels(): string
    {
        return $this->trackingPixels;
    }

    private function setStoreNumber(string $storeNumber): void
    {
        $this->storeNumber = $storeNumber;
    }

    public function getStoreNumber(): string
    {
        return $this->storeNumber;
    }

    private function setZipcode(string $zipcode): void
    {
        $this->zipcode = $zipcode;
    }

    public function getZipcode(): string
    {
        return $this->zipcode;
    }

    private function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function toArray(): array
    {
        return [
            'pdfUrl' => $this->pdfUrl,
            'integration' => $this->integration,
            'sales_region' => $this->salesRegion,
            'brochureNumber' => $this->brochureNumber,
            'title' => $this->title,
            'variety' => $this->variety ?: self::DEFAULT_VARIETY,
            'validFrom' => $this->validFrom,
            'validTo' => $this->validTo,
            'storeNumber' => $this->storeNumber,
            'visibleFrom' => $this->visibleFrom,
            'pdfProcessingOptions' => $this->pdfProcessingOptions ?: self::DEFAULT_PROCESSING_OPTIONS,
            'trackingPixels' => $this->trackingPixels,
            'layout' => $this->layout,
            'type' => $this->type ?: self::DEFAULT_TYPE,
        ];
    }
}
