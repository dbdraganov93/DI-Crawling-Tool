<?php

namespace App\Dto;

class Store extends AbstractDto
{
    private string $integration = '';
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
    private string $addressExtra = '';

    public function toArray(): array
    {
        return [
            'integration' => $this->getIntegration(),
            'storeNumber' => $this->getStoreNumber(),
            'city' => $this->getCity(),
            'zipcode' => $this->getZipcode(),
            'street' => $this->getStreet(),
            'streetNumber' => $this->getStreetNumber(),
            'latitude' => $this->getLatitude(),
            'longitude' => $this->getLongitude(),
            'title' => $this->getTitle(),
            'subtitle' => $this->getSubtitle(),
            'text' => $this->getText(),
            'phone' => $this->getPhone(),
            'fax' => $this->getFax(),
            'email' => $this->getEmail(),
            'storeHours' => $this->getStoreHours(),
            'storeHoursNotes' => $this->getStoreHoursNotes(),
            'payment' => $this->getPayment(),
            'website' => $this->getWebsite(),
            'distribution' => $this->getDistribution(),
            'parking' => $this->getParking(),
            'barrierFree' => $this->getBarrierFree(),
            'bonusCard' => $this->getBonusCard(),
            'section' => $this->getSection(),
            'service' => $this->getService(),
            'toilet' => $this->getToilet(),
            'defaultRadius' => $this->getDefaultRadius(),
            'addressExtra' => $this->getAddressExtra(),
        ];
    }

    protected function setIntegration(string $integration): self
    {
        $this->integration = $integration;

        return $this;
    }

    public function getIntegration(): string
    {
        return $this->integration;
    }

    protected function setStoreNumber(string $storeNumber): self
    {
        $this->storeNumber = $storeNumber;

        return $this;
    }

    public function getStoreNumber(): string
    {
        return $this->storeNumber;
    }

    protected function setCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    protected function setZipcode(string $zipcode): self
    {
        $this->zipcode = $zipcode;

        return $this;
    }

    public function getZipcode(): string
    {
        return $this->zipcode;
    }

    protected function setStreet(string $street): self
    {
        $this->street = $street;

        return $this;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    protected function setStreetNumber(string $streetNumber): self
    {
        $this->streetNumber = $streetNumber;

        return $this;
    }

    public function getStreetNumber(): string
    {
        return $this->streetNumber;
    }

    protected function setLatitude(string $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLatitude(): string
    {
        return $this->latitude;
    }

    protected function setLongitude(string $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getLongitude(): string
    {
        return $this->longitude;
    }

    protected function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    protected function setSubtitle(string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function getSubtitle(): string
    {
        return $this->subtitle;
    }

    protected function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    protected function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    protected function setFax(string $fax): self
    {
        $this->fax = $fax;

        return $this;
    }

    public function getFax(): string
    {
        return $this->fax;
    }

    protected function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    protected function setStoreHours(string $storeHours): self
    {
        $this->storeHours = $storeHours;

        return $this;
    }

    public function getStoreHours(): string
    {
        return $this->storeHours;
    }

    protected function setStoreHoursNotes(string $storeHoursNotes): self
    {
        $this->storeHoursNotes = $storeHoursNotes;

        return $this;
    }

    public function getStoreHoursNotes(): string
    {
        return $this->storeHoursNotes;
    }

    protected function setPayment(string $payment): self
    {
        $this->payment = $payment;

        return $this;
    }

    public function getPayment(): string
    {
        return $this->payment;
    }

    protected function setWebsite(string $website): self
    {
        $this->website = $website;

        return $this;
    }

    public function getWebsite(): string
    {
        return $this->website;
    }

    protected function setDistribution(string $distribution): self
    {
        $this->distribution = $distribution;

        return $this;
    }

    public function getDistribution(): string
    {
        return $this->distribution;
    }

    protected function setParking(string $parking): self
    {
        $this->parking = $parking;

        return $this;
    }

    public function getParking(): string
    {
        return $this->parking;
    }

    protected function setBarrierFree(string $barrierFree): self
    {
        $this->barrierFree = $barrierFree;

        return $this;
    }

    public function getBarrierFree(): string
    {
        return $this->barrierFree;
    }

    protected function setBonusCard(string $bonusCard): self
    {
        $this->bonusCard = $bonusCard;

        return $this;
    }

    public function getBonusCard(): string
    {
        return $this->bonusCard;
    }

    protected function setSection(string $section): self
    {
        $this->section = $section;

        return $this;
    }

    public function getSection(): string
    {
        return $this->section;
    }

    protected function setService(string $service): self
    {
        $this->service = $service;

        return $this;
    }

    public function getService(): string
    {
        return $this->service;
    }

    protected function setToilet(string $toilet): self
    {
        $this->toilet = $toilet;

        return $this;
    }

    public function getToilet(): string
    {
        return $this->toilet;
    }

    protected function setDefaultRadius(string $defaultRadius): self
    {
        $this->defaultRadius = $defaultRadius;

        return $this;
    }

    public function getDefaultRadius(): string
    {
        return $this->defaultRadius;
    }

    protected function setAddressExtra(string $addressExtra): self
    {
        $this->addressExtra = $addressExtra;

        return $this;
    }

    public function getAddressExtra(): string
    {
        return $this->addressExtra;
    }
}
