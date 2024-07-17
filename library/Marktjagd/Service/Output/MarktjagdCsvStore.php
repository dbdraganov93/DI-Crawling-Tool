<?php

/**
 * Service zum Generieren der Marktjagd-CSV
 */
class Marktjagd_Service_Output_MarktjagdCsvStore extends Marktjagd_Service_Output_MarktjagdCsvAbstract
{

    protected $_eAdditionalInfos;
    protected $_companyId;
    protected $_apiCheck;
    protected $_jLanguageInfos;
    protected $_company;

    /**
     * Konstruktor
     *
     * @param int $companyId Unternehmens-ID
     * @param bool apiCheck Soll via API die aktuelle Anzahl der Standorte gecheckt werden?
     * @param string $modus Modus
     */
    public function __construct($companyId, $apiCheck = true, $modus = 'w')
    {
        $this->_type = 'stores';
        $this->_companyId = $companyId;
        $this->_apiCheck = $apiCheck;
        parent::__construct($this->_companyId, $modus);
        $sAdditionalInfos = new Marktjagd_Database_Service_CompanyAdditionalInfos();
        $this->_eAdditionalInfos = $sAdditionalInfos->findByCompanyId($this->_companyId);

        $sCompany = new Marktjagd_Database_Service_Company();
        $this->_company = $sCompany->find($companyId);

        $this->_jLanguageInfos = json_decode(file_get_contents(APPLICATION_PATH . '/../public/files/dataCh/CH_PLZ_Language.json'));
    }

    /**
     * @param Marktjagd_Collection_Api_Store $collection
     * @return string
     * @throws Zend_Config_Exception
     */
    public function generateContent($collection)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        if ($this->_apiCheck) {
            $crawlerConfigFile = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            $countStores = count($sApi->findStoresByCompany($this->_companyId)->getElements());
            $countCollection = count($collection->getElements());
            if ($countStores && ($countCollection / $countStores < $crawlerConfigFile->crawler->stores->difference)) {
                throw new Exception('companyId: ' . $this->_companyId . ' - stores in collection: ' . $countCollection . ' - stores in core: ' . $countStores);
            }
        }

        // update stores (dealer feedback)
        $sUpdateStores = new Marktjagd_Service_Compare_Update_Store();
        $collection = $sUpdateStores->updateCollection($collection, $this->_companyId);

        $elements = $collection->getElements();
        $headline = $collection->getHeadline();

        $aCompanyInfos = $sApi->findCompanyByCompanyId($this->_companyId, '3');
        $foodStore = FALSE;
        if ($aCompanyInfos) {
            $foodStore = TRUE;
            $sDbGeoRegion = new Marktjagd_Database_Service_GeoRegion();
            $aValidParisienZipcodes = array();
            $aValidParisienSurroundingZipcodes = array();
            foreach ($sDbGeoRegion->findAllZipcodesForParis() as $singleZipcode) {
                $aValidParisienZipcodes[] = $singleZipcode->getZipcode();
            }
            foreach ($sDbGeoRegion->findAllZipcodesForParis() as $singleZipcode) {
                $aValidParisienSurroundingZipcodes[] = $singleZipcode->getZipcode();
            }
        }

        $csvString = $headline . "\n";
        /* @var Marktjagd_Entity_Api_Store $element */
        foreach ($elements as $element) {
            if (preg_match('#^(2)$#', $this->_company->getIdPartner())) {
                $element = $this->addLanguageDistribution($element);
            }

            if (!is_null($this->_eAdditionalInfos->getIdAdditionalInfos())) {
                if (!strlen($element->getBonusCard())) {
                    $element->setBonusCard($this->_eAdditionalInfos->getBonusCards());
                }
                if (!strlen($element->getToilet())) {
                    $element->setToilet($this->_eAdditionalInfos->getToilet());
                }
                if (!strlen($element->getService())) {
                    $element->setService($this->_eAdditionalInfos->getServices());
                }
                if (!strlen($element->getSection())) {
                    $element->setSection($this->_eAdditionalInfos->getSection());
                }
                if (!strlen($element->getParking())) {
                    $element->setParking($this->_eAdditionalInfos->getParking());
                }
                if (!strlen($element->getBarrierFree())) {
                    $element->setBarrierFree($this->_eAdditionalInfos->getBarrierFree());
                }
                if (!strlen($element->getPayment())) {
                    $element->setPayment($this->_eAdditionalInfos->getPayment());
                }
            }

            if ($foodStore && in_array($element->getZipcode(), $aValidParisienZipcodes)) {
                $element->setDefaultRadius(2);
            }

            if ($foodStore && in_array($element->getZipcode(), $aValidParisienSurroundingZipcodes)) {
                $element->setDefaultRadius(10);
            }

            $csvString .= $this->generateContentLine($element);
        }

        return $csvString;
    }

    /**
     * @param Marktjagd_Entity_Api_Store $element
     * @return string
     */
    public function generateContentLine($element)
    {
        $csvString = '"' . $element->getStoreNumber() . '";'
            . '"' . $element->getCity() . '";'
            . '"' . $element->getZipcode() . '";'
            . '"' . $element->getStreet() . '";'
            . '"' . $element->getStreetNumber() . '";'
            . '"' . $element->getLatitude() . '";'
            . '"' . $element->getLongitude() . '";'
            . '"' . $element->getTitle() . '";'
            . '"' . $element->getSubtitle() . '";'
            . '"' . $element->getText() . '";'
            . '"' . $element->getPhone() . '";'
            . '"' . $element->getFax() . '";'
            . '"' . $element->getEmail() . '";'
            . '"' . $element->getStoreHours() . '";'
            . '"' . $element->getStoreHoursNotes() . '";'
            . '"' . $element->getPayment() . '";'
            . '"' . $element->getWebsite() . '";'
            . '"' . $element->getDistribution() . '";'
            . '"' . $element->getParking() . '";'
            . '"' . $element->getBarrierFree() . '";'
            . '"' . $element->getBonusCard() . '";'
            . '"' . $element->getSection() . '";'
            . '"' . $element->getService() . '";'
            . '"' . $element->getToilet() . '";'
            . '"' . $element->getDefaultRadius() . '"'
            . "\n";

        return $csvString;
    }

    /**
     * @param $eStore
     * @return mixed
     */
    public function addLanguageDistribution($eStore)
    {
        foreach ($this->_jLanguageInfos as $key => $zipcodes) {
            if (in_array($eStore->getZipcode(), $zipcodes)) {
                $strDistribution = $eStore->getDistribution();
                if (strlen($strDistribution)) {
                    $strDistribution .= ',';
                }

                $strDistribution .= $key;
                $eStore->setDistribution($strDistribution);
            }
        }
        return $eStore;
    }
}
