<?php

class Marktjagd_Entity_Api_Store extends Marktjagd_Entity_Api_Abstract
{

    protected $id;
    protected $storeNumber;
    protected $title;
    protected $subtitle;
    protected $text;
    protected $zipcode;
    protected $city;
    protected $street;
    protected $streetNumber;
    protected $distribution;
    protected $latitude;
    protected $longitude;
    protected $phone;
    protected $fax;
    protected $email;
    protected $storeHours;
    protected $storeHoursNotes;
    protected $payment;
    protected $image;
    protected $website;
    protected $logo;
    protected $parking;
    protected $barrierFree;
    protected $bonusCard;
    protected $section;
    protected $service;
    protected $toilet;
    protected $defaultRadius;

    /**
     * @param int $id
     * @return Marktjagd_Entity_Api_Store
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $city
     * @return Marktjagd_Entity_Api_Store
     */
    public function setCity($city)
    {
        $this->city = ucwords(strip_tags($city));
        return $this;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @param string $distribution
     * @return Marktjagd_Entity_Api_Store
     */
    public function setDistribution($distribution)
    {
        $this->distribution = $distribution;
        return $this;
    }

    /**
     * @return string
     */
    public function getDistribution()
    {
        return $this->distribution;
    }

    /**
     * @param string $email
     * @return Marktjagd_Entity_Api_Store
     */
    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $fax
     * @return Marktjagd_Entity_Api_Store
     */
    public function setFax($fax)
    {
        $this->fax = $fax;
        return $this;
    }

    /**
     * @param string $fax
     * @return Marktjagd_Entity_Api_Store
     */
    public function setFaxNormalized($fax)
    {
        $sAddress = new Marktjagd_Service_Text_Address();

        $this->fax = $sAddress->normalizePhoneNumber($fax);
        return $this;
    }

    /**
     * @return string
     */
    public function getFax()
    {
        return $this->fax;
    }

    /**
     * @param string $image
     * @return Marktjagd_Entity_Api_Store
     */
    public function setImage($image)
    {
        $this->image = $image;
        return $this;
    }

    /**
     * @return string
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * @param string $latitude
     * @return Marktjagd_Entity_Api_Store
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;
        return $this;
    }

    /**
     * @return string
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * @param string $logo
     * @return Marktjagd_Entity_Api_Store
     */
    public function setLogo($logo)
    {
        $this->logo = $logo;
        return $this;
    }

    /**
     * @return string
     */
    public function getLogo()
    {
        return $this->logo;
    }

    /**
     * @param string $longitude
     * @return Marktjagd_Entity_Api_Store
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;
        return $this;
    }

    /**
     * @return string
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * @param string $payment
     * @return Marktjagd_Entity_Api_Store
     */
    public function setPayment($payment)
    {
        $this->payment = $payment;
        return $this;
    }

    /**
     * @return string
     */
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * @param string $phone
     * @return Marktjagd_Entity_Api_Store
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;
        return $this;
    }

    /**
     * @param string $phone
     * @return Marktjagd_Entity_Api_Store
     */
    public function setPhoneNormalized($phone)
    {
        $sAddress = new Marktjagd_Service_Text_Address();

        $this->phone = $sAddress->normalizePhoneNumber($phone);
        return $this;
    }

    /**
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @param string $storeHours
     * @return Marktjagd_Entity_Api_Store
     */
    public function setStoreHours($storeHours)
    {
        $this->storeHours = $storeHours;
        return $this;
    }

    /**
     * @param string $storeHours
     * @return Marktjagd_Entity_Api_Store
     */
    public function setStoreHoursNormalized($storeHours, $type = 'text', $splitByDays = FALSE, $strLanguage = '')
    {
        $sTimes = new Marktjagd_Service_Text_Times();

        $this->storeHours = $sTimes->generateMjOpenings($storeHours, $type, $splitByDays, $strLanguage);
        return $this;
    }

    /**
     * @return string
     */
    public function getStoreHours()
    {
        return $this->storeHours;
    }

    /**
     * @param string $storeHoursNotes
     * @return Marktjagd_Entity_Api_Store
     */
    public function setStoreHoursNotes($storeHoursNotes)
    {
        $this->storeHoursNotes = $storeHoursNotes;
        return $this;
    }

    /**
     * @return string
     */
    public function getStoreHoursNotes()
    {
        return $this->storeHoursNotes;
    }

    /**
     * @param string $storeNumber
     * @return Marktjagd_Entity_Api_Store
     */
    public function setStoreNumber($storeNumber)
    {
        $this->storeNumber = $storeNumber;
        return $this;
    }

    /**
     * @return string
     */
    public function getStoreNumber()
    {
        return $this->storeNumber;
    }

    /**
     * @param string $street
     * @return Marktjagd_Entity_Api_Store
     */
    public function setStreet($street)
    {
        $sAddress = new Marktjagd_Service_Text_Address();
        $this->street = $sAddress->normalizeStreet($street);
        return $this;
    }

    /**
     * @return string
     */
    public function getStreet()
    {
        return $this->street;
    }

    /**
     * @param string $streetNumber
     * @return Marktjagd_Entity_Api_Store
     */
    public function setStreetNumber($streetNumber)
    {
        $this->streetNumber = strip_tags($streetNumber);
        return $this;
    }

    /**
     * @return string
     */
    public function getStreetNumber()
    {
        return $this->streetNumber;
    }

    /**
     * @param string $subtitle
     * @return Marktjagd_Entity_Api_Store
     */
    public function setSubtitle($subtitle)
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubtitle()
    {
        return $this->subtitle;
    }

    /**
     * @param string $text
     * @return Marktjagd_Entity_Api_Store
     */
    public function setText($text)
    {
        $this->text = $text;
        return $this;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param string $title
     * @return Marktjagd_Entity_Api_Store
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $website
     * @return Marktjagd_Entity_Api_Store
     */
    public function setWebsite($website)
    {
        $this->website = $website;
        return $this;
    }

    /**
     * @return string
     */
    public function getWebsite()
    {
        return $this->website;
    }

    /**
     * @param string $zipcode
     * @return Marktjagd_Entity_Api_Store
     */
    public function setZipcode($zipcode)
    {
        $this->zipcode = $zipcode;
        return $this;
    }

    /**
     * @return string
     */
    public function getZipcode()
    {
        return $this->zipcode;
    }

    /**
     * @return int
     */
    public function getBarrierFree()
    {
        return $this->barrierFree;
    }

    /**
     * @param bool $barrierFree
     * @return Marktjagd_Entity_Api_Store
     */
    public function setBarrierFree($barrierFree)
    {
        $this->barrierFree = $barrierFree;
        return $this;
    }

    /**
     * @return string
     */
    public function getBonusCard()
    {
        return $this->bonusCard;
    }

    /**
     * @param string $bonusCard
     * @return Marktjagd_Entity_Api_Store
     */
    public function setBonusCard($bonusCard)
    {
        $this->bonusCard = $bonusCard;
        return $this;
    }

    /**
     * @return int
     */
    public function getDefaultRadius()
    {
        return $this->defaultRadius;
    }

    /**
     * @param int $defaultRadius
     * @return Marktjagd_Entity_Api_Store
     */
    public function setDefaultRadius($defaultRadius)
    {
        $this->defaultRadius = $defaultRadius;
        return $this;
    }

    /**
     * @return string
     */
    public function getParking()
    {
        return $this->parking;
    }

    /**
     * @param string $parking
     * @return Marktjagd_Entity_Api_Store
     */
    public function setParking($parking)
    {
        $this->parking = $parking;
        return $this;
    }

    /**
     * @return string
     */
    public function getSection()
    {
        return $this->section;
    }

    /**
     * @param string $section
     * @return Marktjagd_Entity_Api_Store
     */
    public function setSection($section)
    {
        $this->section = $section;
        return $this;
    }

    /**
     * @return string
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @param string $service
     * @return Marktjagd_Entity_Api_Store
     */
    public function setService($service)
    {
        $this->service = $service;
        return $this;
    }

    /**
     * @return int
     */
    public function getToilet()
    {
        return $this->toilet;
    }

    /**
     * @param int $toilet
     * @return Marktjagd_Entity_Api_Store
     */
    public function setToilet($toilet)
    {
        $this->toilet = $toilet;
        return $this;
    }

    /**
     * @param string $streetAndNumber
     * @param string $zipcodeAndCity
     * @return Marktjagd_Entity_Api_Store
     */
    public function setAddress($streetAndNumber, $zipcodeAndCity, $countryCode = 'DE')
    {
        $this->setStreetAndStreetNumber($streetAndNumber, $countryCode)
            ->setZipcodeAndCity($zipcodeAndCity);

        return $this;
    }

    /**
     * @param string $streetAndNumber
     * @return Marktjagd_Entity_Api_Store
     */
    public function setStreetAndStreetNumber($streetAndNumber, $localCode = 'DE')
    {
        $localCode = strtoupper(substr($localCode, 0, 2));

        $sAddress = new Marktjagd_Service_Text_Address();

        if (preg_match('#^FR$#', $localCode)) {
            $pattern = '#(\d+)\s*([\/-]\s*\d+|\w\s*([\/-]\s*\w)?)?[,;]?\s+([\D]{2,})#';
            if (preg_match($pattern, trim($streetAndNumber), $streetMatch)) {
                $streetAndNumber = $streetMatch[4] . ' ' . $streetMatch[1] . ' ' . $streetMatch[2];
            }
        }

        $this->setStreet($sAddress->extractAddressPart('street', $streetAndNumber, $localCode))
            ->setStreetNumber($sAddress->extractAddressPart('street_number', $streetAndNumber));

        if (preg_match('#^FR$#', $localCode)) {
            $this->setStreet(preg_replace(array('#^rn$#i', '#^rd$#i'), array('route nationale', 'route départementale'), $this->getStreet()));
        }

        if (preg_match('#^CH$#', $localCode)) {
            $this->setStreet(preg_replace(array('#ß#i'), array('ss'), $this->getStreet()));
        }

        return $this;
    }

    /**
     * @param string $zipcodeAndCity
     * @return Marktjagd_Entity_Api_Store
     */
    public function setZipcodeAndCity($zipcodeAndCity)
    {
        $sAddress = new Marktjagd_Service_Text_Address();

        $this->setZipcode($sAddress->extractAddressPart('zipcode', $zipcodeAndCity))
            ->setCity($sAddress->extractAddressPart('city', $zipcodeAndCity));

        return $this;
    }

    /**
     * @param bool $ignoreStoreNumberForHash
     * @return string
     */
    public function getHash($ignoreStoreNumberForHash = false)
    {
        $storeNumber = $this->getStoreNumber();
        if (strlen($storeNumber) && !$ignoreStoreNumberForHash
        ) {
            $hash = $storeNumber;
        } else {
            $sAddress = new Marktjagd_Service_Text_Address();
            $street = $sAddress->normalizeStreet($this->getStreet());

            $hash = md5(
                $this->getZipcode()
                . $street
                . $this->getStreetNumber());
        }

        return $hash;
    }

}
