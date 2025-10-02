<?php

declare(strict_types=1);

namespace App\Dto;

class Product extends AbstractDto
{
    private const INTEGRATION_URL = 'https://iproto.offerista.com/api/integrations/';

    /**
     * Headers used when exporting products to CSV.
     */
    public const CSV_HEADERS = [
        'integration',
        'productNumber',
        'title',
        'description',
        'price',
        'currency',
        'secondaryPrice',
        'secondaryCurrency',
        'priceIsVariable',
        'manufacturerPrice',
        'secondaryManufacturerPrice',
        'manufacturerNumber',
        'gtin',
        'languageCode',
        'keywords',
        'trackingPixels',
        'url',
        'brandText',
        'brandImage',
        'amount',
        'size',
        'color',
        'unitType',
        'subTitle',
        'salesRegion',
        'validFrom',
        'validTo',
        'visibleFrom',
        'hidden',
        'additionalProperties',
        'shipping',
        'variants',
    ];

    private string $integration = '';
    private string $productNumber = '';
    private string $title = '';
    private string $description = '';
    private string $price = '';
    private string $currency = '';
    private string $secondaryPrice = '';
    private string $secondaryCurrency = '';
    private string $priceIsVariable = '';
    private string $manufacturerPrice = '';
    private string $secondaryManufacturerPrice = '';
    private string $manufacturerNumber = '';
    private string $gtin = '';
    private string $languageCode = '';
    private string $keywords = '';
    private string $trackingPixels = '';
    private string $url = '';
    private string $brandText = '';
    private string $brandImage = '';
    private string $amount = '';
    private string $size = '';
    private string $color = '';
    private string $unitType = '';
    private string $subTitle = '';
    private string $salesRegion = '';
    private string $validFrom = '';
    private string $validTo = '';
    private string $visibleFrom = '';
    private string $hidden = '';
    private string $additionalProperties = '';
    private string $shipping = '';
    private string $variants = '';

    public function toArray(): array
    {
        return [
            'integration' => $this->getIntegration(),
            'productNumber' => $this->getProductNumber(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'price' => $this->getPrice(),
            'currency' => $this->getCurrency(),
            'secondaryPrice' => $this->getSecondaryPrice(),
            'secondaryCurrency' => $this->getSecondaryCurrency(),
            'priceIsVariable' => $this->getPriceIsVariable(),
            'manufacturerPrice' => $this->getManufacturerPrice(),
            'secondaryManufacturerPrice' => $this->getSecondaryManufacturerPrice(),
            'manufacturerNumber' => $this->getManufacturerNumber(),
            'gtin' => $this->getGtin(),
            'languageCode' => $this->getLanguageCode(),
            'keywords' => $this->getKeywords(),
            'trackingPixels' => $this->getTrackingPixels(),
            'url' => $this->getUrl(),
            'brandText' => $this->getBrandText(),
            'brandImage' => $this->getBrandImage(),
            'amount' => $this->getAmount(),
            'size' => $this->getSize(),
            'color' => $this->getColor(),
            'unitType' => $this->getUnitType(),
            'subTitle' => $this->getSubTitle(),
            'salesRegion' => $this->getSalesRegion(),
            'validFrom' => $this->getValidFrom(),
            'validTo' => $this->getValidTo(),
            'visibleFrom' => $this->getVisibleFrom(),
            'hidden' => $this->getHidden(),
            'additionalProperties' => $this->getAdditionalProperties(),
            'shipping' => $this->getShipping(),
            'variants' => $this->getVariants(),
        ];
    }

    protected function setIntegration(string $integration): void
    {
        if (str_starts_with($integration, 'http')) {
            $this->integration = $integration;

            return;
        }

        $this->integration = self::INTEGRATION_URL . ltrim($integration, '/');
    }

    public function getIntegration(): string
    {
        return $this->integration;
    }

    protected function setProductNumber(string $productNumber): void
    {
        $this->productNumber = $productNumber;
    }

    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    protected function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    protected function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    protected function setPrice(string $price): void
    {
        $this->price = $price;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    protected function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    protected function setSecondaryPrice(string $secondaryPrice): void
    {
        $this->secondaryPrice = $secondaryPrice;
    }

    public function getSecondaryPrice(): string
    {
        return $this->secondaryPrice;
    }

    protected function setSecondaryCurrency(string $secondaryCurrency): void
    {
        $this->secondaryCurrency = $secondaryCurrency;
    }

    public function getSecondaryCurrency(): string
    {
        return $this->secondaryCurrency;
    }

    protected function setPriceIsVariable(string $priceIsVariable): void
    {
        $this->priceIsVariable = $priceIsVariable;
    }

    public function getPriceIsVariable(): string
    {
        return $this->priceIsVariable;
    }

    protected function setManufacturerPrice(string $manufacturerPrice): void
    {
        $this->manufacturerPrice = $manufacturerPrice;
    }

    public function getManufacturerPrice(): string
    {
        return $this->manufacturerPrice;
    }

    protected function setSecondaryManufacturerPrice(string $secondaryManufacturerPrice): void
    {
        $this->secondaryManufacturerPrice = $secondaryManufacturerPrice;
    }

    public function getSecondaryManufacturerPrice(): string
    {
        return $this->secondaryManufacturerPrice;
    }

    protected function setManufacturerNumber(string $manufacturerNumber): void
    {
        $this->manufacturerNumber = $manufacturerNumber;
    }

    public function getManufacturerNumber(): string
    {
        return $this->manufacturerNumber;
    }

    protected function setGtin(string $gtin): void
    {
        $this->gtin = $gtin;
    }

    public function getGtin(): string
    {
        return $this->gtin;
    }

    protected function setLanguageCode(string $languageCode): void
    {
        $this->languageCode = $languageCode;
    }

    public function getLanguageCode(): string
    {
        return $this->languageCode;
    }

    protected function setKeywords(string $keywords): void
    {
        $this->keywords = $keywords;
    }

    public function getKeywords(): string
    {
        return $this->keywords;
    }

    protected function setTrackingPixels(string $trackingPixels): void
    {
        $this->trackingPixels = $trackingPixels;
    }

    public function getTrackingPixels(): string
    {
        return $this->trackingPixels;
    }

    protected function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    protected function setBrandText(string $brandText): void
    {
        $this->brandText = $brandText;
    }

    public function getBrandText(): string
    {
        return $this->brandText;
    }

    protected function setBrandImage(string $brandImage): void
    {
        $this->brandImage = $brandImage;
    }

    public function getBrandImage(): string
    {
        return $this->brandImage;
    }

    protected function setAmount(string $amount): void
    {
        $this->amount = $amount;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    protected function setSize(string $size): void
    {
        $this->size = $size;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    protected function setColor(string $color): void
    {
        $this->color = $color;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    protected function setUnitType(string $unitType): void
    {
        $this->unitType = $unitType;
    }

    public function getUnitType(): string
    {
        return $this->unitType;
    }

    protected function setSubTitle(string $subTitle): void
    {
        $this->subTitle = $subTitle;
    }

    public function getSubTitle(): string
    {
        return $this->subTitle;
    }

    protected function setSalesRegion(string $salesRegion): void
    {
        $this->salesRegion = $salesRegion;
    }

    public function getSalesRegion(): string
    {
        return $this->salesRegion;
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

    protected function setHidden(string $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function getHidden(): string
    {
        return $this->hidden;
    }

    protected function setAdditionalProperties(string $additionalProperties): void
    {
        $this->additionalProperties = $additionalProperties;
    }

    public function getAdditionalProperties(): string
    {
        return $this->additionalProperties;
    }

    protected function setShipping(string $shipping): void
    {
        $this->shipping = $shipping;
    }

    public function getShipping(): string
    {
        return $this->shipping;
    }

    protected function setVariants(string $variants): void
    {
        $this->variants = $variants;
    }

    public function getVariants(): string
    {
        return $this->variants;
    }
}
