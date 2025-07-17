<?php

namespace App\Dto;

class Brochure extends AbstractDto
{
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
    private string $variety = 'leaflet';
    private string $validFrom = '';
    private string $validTo = '';
    private string $visibleFrom = '';
    private array $pdfProcessingOptions = [];
    private string $layout = '';
    private string $trackingPixels = '';
    private string $storeNumber = '';
    private string $zipcode = '';
    private string $type = 'default';
    private string $tags = '';
    private int $national = 0;
    private string $gender = '';
    private string $ageRange = '';
    private string $langCode = '';

    public function toArray(): array
    {
        return [
            'pdfUrl' => $this->getPdfUrl(),
            'integration' => $this->getIntegration(),
            'salesRegion' => $this->getSalesRegion(),
            'brochureNumber' => $this->getBrochureNumber(),
            'title' => $this->getTitle(),
            'variety' => $this->getVariety(),
            'validFrom' => $this->getValidFrom(),
            'validTo' => $this->getValidTo(),
            'storeNumber' => $this->getStoreNumber(),
            'visibleFrom' => $this->getVisibleFrom(),
            'pdfProcessingOptions' => $this->getPdfProcessingOptions(),
            'trackingPixels' => $this->getTrackingPixels(),
            'layout' => $this->getLayout(),
            'type' => $this->getType(),
            'tags' => $this->getTags(),
            'national' => $this->getNational(),
            'gender' => $this->getGender(),
            'ageRange' => $this->getAgeRange(),
            'langCode' => $this->getLangCode(),
        ];
    }

    protected function setPdfUrl(string $pdfUrl): void
    {
        $this->pdfUrl = $pdfUrl;
    }

    public function getPdfUrl(): string
    {
        return $this->pdfUrl;
    }

    protected function setIntegration(string $integration): void
    {
        $this->integration = self::INTEGRATION_URL . $integration;
    }

    public function getIntegration(): string
    {
        return $this->integration;
    }

    protected function setSalesRegion(string $salesRegion): void
    {
        $this->salesRegion = $salesRegion;
    }

    public function getSalesRegion(): string
    {
        return $this->salesRegion;
    }

    protected function setBrochureNumber(string $brochureNumber): void
    {
        $this->brochureNumber = $brochureNumber;
    }

    public function getBrochureNumber(): string
    {
        return $this->brochureNumber;
    }

    protected function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    protected function setVariety(string $variety): void
    {
        $this->variety = $variety;
    }

    public function getVariety(): string
    {
        return $this->variety;
    }

    protected function setValidFrom(string $validFrom): void
    {
        $this->validFrom = $validFrom;
    }

    public function getValidFrom(): string
    {
        return $this->validFrom;
    }

    protected function setValidTo(string $validTo): void
    {
        $this->validTo = $validTo;
    }

    public function getValidTo(): string
    {
        return $this->validTo;
    }

    protected function setVisibleFrom(string $visibleFrom): void
    {
        $this->visibleFrom = $visibleFrom;
    }

    public function getVisibleFrom(): string
    {
        return $this->visibleFrom;
    }

    protected function setPdfProcessingOptions(array $pdfProcessingOptions): void
    {
        $this->pdfProcessingOptions = $pdfProcessingOptions;
    }

    public function getPdfProcessingOptions(): array
    {
        return $this->pdfProcessingOptions = [];
    }

    protected function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    protected function setTrackingPixels(string $trackingPixels): void
    {
        $this->trackingPixels = $trackingPixels;
    }

    public function getTrackingPixels(): string
    {
        return $this->trackingPixels;
    }

    protected function setStoreNumber(string $storeNumber): void
    {
        $this->storeNumber = $storeNumber;
    }

    public function getStoreNumber(): string
    {
        return $this->storeNumber;
    }

    protected function setZipcode(string $zipcode): void
    {
        $this->zipcode = $zipcode;
    }

    public function getZipcode(): string
    {
        return $this->zipcode;
    }

    protected function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    protected function setTags(string $tags): void
    {
        $this->tags = $tags;
    }

    public function getTags(): string
    {
        return $this->tags;
    }

    protected function setNational(int $national): void
    {
        $this->national = $national;
    }

    public function getNational(): int
    {
        return $this->national;
    }

    protected function setGender(string $gender): void
    {
        $this->setGender($gender);
    }

    public function getGender(): string
    {
        return $this->gender;
    }

    protected function setAgeRange(string $ageRange): void
    {
        $this->ageRange = $ageRange;
    }

    public function getAgeRange(): string
    {
        return $this->ageRange;
    }

    protected function setLangCode(string $langCode): void
    {
        $this->langCode = $langCode;
    }

    public function getLangCode(): string
    {
        return $this->langCode;
    }
}
