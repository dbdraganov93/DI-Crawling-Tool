<?php

namespace App\Dto;

use function is_array;
use function is_string;
use function json_decode;
use function json_last_error;

class DiscoverBrochure extends Brochure
{
    public function toArray(): array
    {
        return [
            'brochure_number' => $this->getBrochureNumber(),
            'type' => $this->getType(),
            'url' => $this->getPdfUrl(),
            'title' => $this->getTitle(),
            'tags' => $this->getTags(),
            'start' => $this->getValidFrom(),
            'end' => $this->getValidTo(),
            'visible_start' => $this->getVisibleFrom(),
            'store_number' => $this->getStoreNumber(),
            'distribution' => $this->getSalesRegion(),
            'variety' => $this->getVariety(),
            'national' => $this->getNational(),
            'gender' => $this->getGender(),
            'age_range' => $this->getAgeRange(),
            'tracking_bug' => $this->getTrackingPixels(),
            'options' => $this->getPdfProcessingOptions(),
            'lang_code' => $this->getLangCode(),
            'zipcode' => $this->getZipcode(),
            'layout' => $this->getLayout(),
        ];
    }

    protected function setBrochure_number(string $brochureNumber): void
    {
        $this->setBrochureNumber($brochureNumber);
    }

    protected function setUrl(string $url): void
    {
        $this->setPdfUrl($url);
    }

    protected function setStart(string $start): void
    {
        $this->setValidFrom($start);
    }

    protected function setEnd(string $end): void
    {
        $this->setValidTo($end);
    }

    protected function setVisible_start(string $visibleStart): void
    {
        $this->setVisibleFrom($visibleStart);
    }

    protected function setStore_number(string $storeNumber): void
    {
        $this->setStoreNumber($storeNumber);
    }

    protected function setDistribution(string $distribution): void
    {
        $this->setSalesRegion($distribution);
    }

    protected function setTracking_bug(string $trackingBug): void
    {
        $this->setTrackingPixels($trackingBug);
    }

    protected function setOptions(mixed $options): void
    {
        if (is_string($options) && $options !== '') {
            $decoded = json_decode($options, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $options = $decoded;
            }
        }

        if (!is_array($options)) {
            $options = [];
        }

        $this->setPdfProcessingOptions($options);
    }

    protected function setLang_code(string $langCode): void
    {
        $this->setLangCode($langCode);
    }

    protected function setAge_range(string $ageRange): void
    {
        $this->setAgeRange($ageRange);
    }

    public function getDistribution(): string
    {
        return $this->getSalesRegion();
    }

    public function getUrl(): string
    {
        return $this->getPdfUrl();
    }

    public function getStart(): string
    {
        return $this->getValidFrom();
    }

    public function getEnd(): string
    {
        return $this->getValidTo();
    }

    public function getVisibleStart(): string
    {
        return $this->getVisibleFrom();
    }

    public function getTrackingBug(): string
    {
        return $this->getTrackingPixels();
    }

    public function getOptions(): array
    {
        return $this->getPdfProcessingOptions();
    }
}
