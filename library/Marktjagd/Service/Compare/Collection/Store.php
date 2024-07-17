<?php

/**
 * Klasse zum Vergleich von Standorten
 *
 * Class Marktjagd_Service_Compare_Collection_Store
 */
class Marktjagd_Service_Compare_Collection_Store
{
    public static $_COMPARE_METHOD_SIMPLE = 'simple';

    /**
     * Vergleicht zwei Collections, sollten in der Sekundärquelle mehr Informationen enthalten sein
     * dann werden dies in der Primärquelle angefügt
     *
     * @param Marktjagd_Collection_Api_Store $cStorePrimary Primäre Standort-Quelle
     * @param Marktjagd_Collection_Api_Store $cStoreSecondary Sekundäre Standort-Quelle
     * @param string $compareMethod Art, mit welcher das Abgleich der Standorte erfolgt
     *
     * @return Marktjagd_Collection_Api_Store ergänzte Standortdaten als Collection
     */
    public function updateStores($cStorePrimary, $cStoreSecondary, $compareMethod = 'simple')
    {
        // prüfen, ob Standorte der Primärquelle eine id besitzen, ggf. hinzufügen
        // id wird zur Erzeugung des Mapping zwingend benötigt
        $cStorePrimaryTemp = new Marktjagd_Collection_Api_Store();        
        $cStorePrimaryElems = $cStorePrimary->getElements();
        foreach ($cStorePrimaryElems as $eStorePrimary){
            if ($eStorePrimary->getId() == NULL){
                $eStorePrimary->setId(uniqid());
            }
            $cStorePrimaryTemp->addElement($eStorePrimary);
        }        
        
        // gleiche Standorte finden
        $aStoreMapping = $this->mapStores($cStorePrimaryTemp, $cStoreSecondary, $compareMethod);

        $cStorePrimaryElems = $cStorePrimaryTemp->getElements();
        $cStoresUpdated = new Marktjagd_Collection_Api_Store();
        
        // Daten der Standorte vergleichen und ggf. updaten
        /* @var $eStorePrimary Marktjagd_Entity_Api_Store*/
        foreach ($cStorePrimaryElems as $eStorePrimary) {
            if (array_key_exists($eStorePrimary->getId(), $aStoreMapping)) {                
                // alle Attribute des Entity holen
                $aProperties = $eStorePrimary->getProperties();
                foreach ($aProperties as $propertyName => $propertyValue) {
                    if ($propertyName == 'id'
                        || $propertyName == 'storeNumber'
                        || $propertyName == 'title'
                        || $propertyName == 'subtitle'
                    ) {
                        continue;
                    }
                    // Wenn Wert in der Primärquelle leer und in der Sekundär vorhanden, dann updaten
                    if (!strlen($eStorePrimary->$propertyName)
                        && $aStoreMapping[$eStorePrimary->getId()]->$propertyName
                    ) {
                        $eStorePrimary->$propertyName = $aStoreMapping[$eStorePrimary->getId()]->$propertyName;                       
                    }
                }                                
            }
            // Element in neue Collection schreiben
            $cStoresUpdated->addElement($eStorePrimary);
        }

        return $cStoresUpdated;
    }

    /**
     * Vergleicht zwei Store Collections mit Hilfe der angegebenen Methode
     *
     * @param Marktjagd_Collection_Api_Store $cStorePrimary Primäre Standort-Quelle
     * @param Marktjagd_Collection_Api_Store $cStoreSecondary Sekundäre Standort-Quelle
     * @param string $compareMethod Art, mit welcher das Abgleich der Standorte erfolgt
     *
     * @return array
     */
    public function mapStores($cStorePrimary, $cStoreSecondary, $compareMethod = 'simple')
    {
        switch ($compareMethod) {
            case Marktjagd_Service_Compare_Collection_Store::$_COMPARE_METHOD_SIMPLE:
                return $this->compareBySimpleMatchAddress($cStorePrimary, $cStoreSecondary);
                break;

            default:
                break;
        }
    }

    /**
     * Vergleicht zwei Store Collections und gibt Mapping zurück, einfacher Addressvergleich
     *
     * @param Marktjagd_Collection_Api_Store $cStorePrimary, erste Collection (primär)
     * @param Marktjagd_Collection_Api_Store $cStoreSecondary, zweite Collection (sekundär)
     *
     * @return array
     */
    protected function compareBySimpleMatchAddress($cStorePrimary, $cStoreSecondary)
    {
        $mjAddress = new Marktjagd_Service_Text_Address();

        $cStorePrimary = $cStorePrimary->getElements();
        $cStoreSecondary = $cStoreSecondary->getElements();

        $aUsedStoreSecondary = array();
        $aUsedStorePrimary = array();
        $aStorePrimary = array();
        $aStoreSecondary = array();

        foreach ($cStoreSecondary as $eStoreSecondary) {
            /* @var $eStoreSecondary Marktjagd_Entity_Api_Store */
            foreach ($cStorePrimary as $eStorePrimary) {
                /* @var $eStorePrimary Marktjagd_Entity_Api_Store */
                $aStorePrimary[$eStorePrimary->getId()] = $eStorePrimary;
                if (($mjAddress->normalizeStreet($eStoreSecondary->getStreet())
                        == $mjAddress->normalizeStreet($eStorePrimary->getStreet()))
                        && ($mjAddress->normalizeStreetNumber((string)$eStoreSecondary->getStreetNumber())
                                == $mjAddress->normalizeStreetNumber((string)$eStorePrimary->getStreetNumber()))
                        && (trim($eStoreSecondary->getZipCode()) == trim($eStorePrimary->getZipCode()))) {
                    $aStoreSecondary[$eStorePrimary->getId()] = $eStoreSecondary;
                    $aUsedStorePrimary[$eStorePrimary->getId()] = $eStorePrimary;

                    if (!isset($aUsedStoreSecondary[$eStorePrimary->getId()])) {
                        $aUsedStoreSecondary[$eStorePrimary->getId()] = $eStoreSecondary;
                    } else {
                        foreach ($cStoreSecondary as $eStoreSecondary) {
                            if (($mjAddress->normalizeStreet($eStoreSecondary->getStreet())
                                    == $mjAddress->normalizeStreet($eStorePrimary->getStreet()))
                                    && ($mjAddress->normalizeStreetNumber((string)$eStoreSecondary->getStreetNumber())
                                            == $mjAddress->normalizeStreetNumber((string)$eStorePrimary->getStreetNumber()))
                                    && ($eStoreSecondary->getZipCode() == $eStorePrimary->getZipCode())
                                    && (substr($eStoreSecondary->getLatitude(), 0, 7)
                                            == substr($eStorePrimary->getLatitude(), 0, 7))
                                    && (substr($eStoreSecondary->getLongitude(), 0, 7)
                                            == substr($eStorePrimary->getLongitude(), 0, 7))
                                    ) {
                                $aStoreSecondary[$eStorePrimary->getId()] = $eStoreSecondary;
                                $aUsedStoreSecondary[$eStorePrimary->getId()] = $eStoreSecondary;
                            }
                        }
                    }
                }
            }
        }

        foreach ($cStoreSecondary as $eStoreSecondary) {
            if (in_array($eStoreSecondary, $aUsedStoreSecondary)) {
                continue;
            }
            foreach ($cStorePrimary as $eStorePrimary) {
                if (($mjAddress->normalizeStreet($eStoreSecondary->getStreet())
                        == $mjAddress->normalizeStreet($eStorePrimary->getStreet()))
                        && (trim($eStoreSecondary->getZipCode()) == trim($eStorePrimary->getZipCode()))) {
                    if (!isset($aUsedStoreSecondary[$eStorePrimary->getId()])) {
                        $aStoreSecondary[$eStorePrimary->getId()] = $eStoreSecondary;
                        $aUsedStoreSecondary[$eStorePrimary->getId()] = $eStoreSecondary;
                    }
                }
            }
        }

        foreach ($cStoreSecondary as $eStoreSecondary) {
            if (in_array($eStoreSecondary, $aUsedStoreSecondary)) {
                continue;
            }
            foreach ($cStorePrimary as $eStorePrimary) {
                if (trim($eStoreSecondary->getZipCode()) == trim($eStorePrimary->getZipCode())) {
                    if (!isset($aUsedStoreSecondary[$eStorePrimary->getStoreNumber()])) {
                        $aStoreSecondary[$eStorePrimary->getId()] = $eStoreSecondary;
                        $aUsedStoreSecondary[$eStorePrimary->getId()] = $eStoreSecondary;
                    }
                }
            }
        }

        return $aUsedStoreSecondary;
    }
    
    public function generateAddressHash2($city, $zipcode, $street, $streetNumber)
    {
        $city = trim(strtolower($city));
        $city = preg_replace('#^(alt|am|bad|groß|gross|klein|markt|neu|sankt|st)[\. -]+#', '', $city);
        $words = preg_split('#[ -/]+#', $city);
        $city = $words[0];

        $zipcode = trim($zipcode);
        if(strlen($zipcode) < 5) {
                $zipcode = str_pad($zipcode, 5 ,'0', STR_PAD_LEFT);
        }

        $street = trim(strtolower($street));
        $street = preg_replace(array('#str[\. -]+#', '#str$#'), 'strasse', $street);
        $street = preg_replace(array('#pl[\. -]+#', '#pl$#'), 'platz', $street);
        $street = preg_replace(array('#\(.*\)#'), '', $street);
        $street = preg_replace(array('#([0-9]+)[ -]+[0-9]+.*#'), '$1', $street);

        $streetNumber = trim($streetNumber);
        if(preg_match('#^[0-9]+\s*(\w(?!\w+))*(\s*[-+\/]\s*)?(?(2)[0-9]+\s*(\w(?!\w+))*|)#', $streetNumber, $matches)) {
            $streetNumber = $matches[0];
        }

        $string = $city.$zipcode.$street;
        $string = str_replace(array('.', ' ', '-', '+'), '', $string);

        $streetNumber = str_replace(array('.', ' '), '', $streetNumber);
        $string .= $streetNumber;

        $string = str_replace(array('ä', 'ü', 'ö', 'ß'), array('ae', 'ue', 'oe', 'ss'), $string);

        return md5($string);
    }
}

