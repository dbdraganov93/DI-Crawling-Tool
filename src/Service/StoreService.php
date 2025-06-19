<?php

declare(strict_types=1);

namespace App\Service;

class StoreService
{
    private int $companyId;
    private array $stores = [];

    // Declare all the properties for a store
    private string $storeNumber = '';
    private string $city = '';
    private string $zipcode = '';
    private string $street = '';
    private string $streetNumber = '';
    private string $latitude = '';
    private string $longitude = '';
    private string $title = '';
    private string $subtitle = '';
    private string $text = '';
    private string $phone = '';
    private string $fax = '';
    private string $email = '';
    private string $storeHours = '';
    private string $storeHoursNotes = '';
    private string $payment = '';
    private string $website = '';
    private string $distribution = '';
    private string $parking = '';
    private string $barrierFree = '';
    private string $bonusCard = '';
    private string $section = '';
    private string $service = '';
    private string $toilet = '';
    private string $defaultRadius = '';

    public function __construct(int $companyId)
    {
        $this->companyId = $companyId;
    }

    public function getCompanyId(): int
    {
        return $this->companyId;
    }

    // Add methods for setting each property
    public function setStoreNumber(string $storeNumber): self
    {
        $this->storeNumber = $storeNumber;
        return $this;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function setZipcode(string $zipcode): self
    {
        $this->zipcode = $zipcode;
        return $this;
    }

    public function setStreet(string $street): self
    {
        $this->street = $street;
        return $this;
    }

    public function setStreetNumber(string $streetNumber): self
    {
        $this->streetNumber = $streetNumber;
        return $this;
    }

    public function setLatitude(string $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function setLongitude(string $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function setSubtitle(string $subtitle): self
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function setFax(string $fax): self
    {
        $this->fax = $fax;
        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function setStoreHours(string $storeHours): self
    {
        $this->storeHours = $storeHours;
        return $this;
    }

    public function setStoreHoursNotes(string $storeHoursNotes): self
    {
        $this->storeHoursNotes = $storeHoursNotes;
        return $this;
    }

    public function setPayment(string $payment): self
    {
        $this->payment = $payment;
        return $this;
    }

    public function setWebsite(string $website): self
    {
        $this->website = $website;
        return $this;
    }

    public function setDistribution(string $distribution): self
    {
        $this->distribution = $distribution;
        return $this;
    }

    public function setParking(string $parking): self
    {
        $this->parking = $parking;
        return $this;
    }

    public function setBarrierFree(string $barrierFree): self
    {
        $this->barrierFree = $barrierFree;
        return $this;
    }

    public function setBonusCard(string $bonusCard): self
    {
        $this->bonusCard = $bonusCard;
        return $this;
    }

    public function setSection(string $section): self
    {
        $this->section = $section;
        return $this;
    }

    public function setService(string $service): self
    {
        $this->service = $service;
        return $this;
    }

    public function setToilet(string $toilet): self
    {
        $this->toilet = $toilet;
        return $this;
    }

    public function setDefaultRadius(string $defaultRadius = '10km'): self
    {
        $this->defaultRadius = $defaultRadius;
        return $this;
    }

    // Add the current store to the list
    public function addCurrentStore(): self
    {
        $this->stores[] = [
            'integration' => '/api/integrations/' . $this->companyId,
            'storeNumber' => $this->storeNumber,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->text,
            'postalCode' => $this->zipcode,
            'city' => $this->city,
            'street' => $this->street,
            'streetNumber' => $this->streetNumber,
            'addressExtra' => '',
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'email' => $this->email,
            'url' => $this->website,
            'openingHoursNotes' => $this->storeHoursNotes,
            'paymentOptions' => $this->payment,
            'parkingOptions' => $this->parking,
            'barrierFree' => strtolower($this->barrierFree) === 'yes',
            'bonusCards' => $this->bonusCard,
            'sections' => $this->section,
            'services' => $this->service,
            'customerToilet' => strtolower($this->toilet) === 'yes',
            'visibilityRadius' => (int) filter_var($this->defaultRadius, FILTER_SANITIZE_NUMBER_INT),
            'placeId' => '',
            'phone' => $this->phone,
            'fax' => $this->fax,
            'openingHours' => [],
        ];

        return $this;
    }


    public function getStores(): array
    {
        return $this->stores;
    }
}
