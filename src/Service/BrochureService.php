<?php

namespace App\Service;


class BrochureService
{
    private const DEFAULT_VARIETY = 'leaflet';

    private const DEFAULT_PROCESSING_OPTIONS = [
        'version' => '2021-04-19',
        'cutPages' => true,
        'dpi' => 250,
        'maxImageSize' => 6250000,
        'allowFontSubstitution' => true,
    ];
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
    private string $timeZone;

    public function __construct(int $companyId, string $timeZone)
    {
        $this->companyId = $companyId;
        $this->timeZone = $timeZone;
        $this->setIntegration($this->companyId);

    }

    public function getTimezone()
    {
        return new \DateTimeZone($this->timeZone);
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
        $this->integration = 'https://iproto.offerista.com/api/integrations/'.$integration;
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

        $this->validFrom = (new \DateTimeImmutable($validFrom, $this->getTimezone()))->format('Y-m-d\TH:i:s e');
        return $this;
    }

    public function setValidTo(string $validTo): self
    {
        $this->validTo = (new \DateTimeImmutable($validTo, $this->getTimezone()))->format('Y-m-d\TH:i:s e');
        return $this;
    }

    public function setVisibleFrom(string $visibleFrom): self
    {
        $this->visibleFrom = (new \DateTimeImmutable($visibleFrom, $this->getTimezone()))->format('Y-m-d\TH:i:s e');
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
//        [
//                'pdfUrl' => $brochureData['pdf_url'],
//                'integration' => $integrationUrl,
//                'brochureNumber' => 'test_' . $brochureData['brochure_number'],
//                'title' => $brochureData['title'],
//                'variety' => $brochureData['variety'],
//                'validFrom' => (new \DateTimeImmutable($brochureData['valid_from'], $tz))->format('Y-m-d\TH:i:s e'),
//                'validTo' => (new \DateTimeImmutable($brochureData['valid_to'], $tz))->format('Y-m-d\TH:i:s e'),
//                'visibleFrom' => (new \DateTimeImmutable($brochureData['visible_from'], $tz))->format('Y-m-d\TH:i:s e'),
//                'pdfProcessingOptions' => $brochureData['pdf_processing_options'] ?: [
//                    'version' => '2021-04-19',
//                    'cutPages' => true,
//                    'dpi' => 250,
//                    'maxImageSize' => 6250000,
//                    'allowFontSubstitution' => true,
//                ],
//                'trackingPixels' => $brochureData['tracking_pixels'] ?? [],
//            ];


        $this->brochures[] = [
            'pdfUrl' => $this->pdfUrl,
            'integration' => $this->integration,
            'sales_region' => $this->salesRegion,
            'brochureNumber' => 't'.$this->brochureNumber,
            'title' => $this->title,
            'variety' => $this->variety ?? self::DEFAULT_VARIETY,
            'validFrom' => $this->validFrom,
            'validTo' => $this->validTo,
            'visibleFrom' => $this->visibleFrom,
            'pdfProcessingOptions' => $this->pdfProcessingOptions ?? self::DEFAULT_PROCESSING_OPTIONS,
            'trackingPixels' => $this->trackingPixels ?? '',
        ];

        return $this;
    }

    public function buildIprotoPayloads(\DateTimeZone $tz, string $integrationUrl): array
    {
        $payloads = [];

        foreach ($this->getBrochures() as $brochureData) {
            $payloads[] = [
                'pdfUrl' => $brochureData['pdf_url'],
                'integration' => $integrationUrl,
                'brochureNumber' => 'test_' . $brochureData['brochure_number'],
                'title' => $brochureData['title'],
                'variety' => $brochureData['variety'],
                'validFrom' => (new \DateTimeImmutable($brochureData['valid_from'], $tz))->format('Y-m-d\TH:i:s e'),
                'validTo' => (new \DateTimeImmutable($brochureData['valid_to'], $tz))->format('Y-m-d\TH:i:s e'),
                'visibleFrom' => (new \DateTimeImmutable($brochureData['visible_from'], $tz))->format('Y-m-d\TH:i:s e'),
                'pdfProcessingOptions' => $brochureData['pdf_processing_options'] ?: [
                    'version' => '2021-04-19',
                    'cutPages' => true,
                    'dpi' => 250,
                    'maxImageSize' => 6250000,
                    'allowFontSubstitution' => true,
                ],
                'trackingPixels' => $brochureData['tracking_pixels'] ?? [],
            ];
        }

        return $payloads;
    }


    public function getBrochures(): array
    {
        return $this->brochures;
    }
}
