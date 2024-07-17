<?php

/**
 * Storecrawler I&M Bauzentrum (ID: 69897)
 */
class Crawler_Company_IundM_Store extends Crawler_Generic_Company
{
    protected $_baseUrl = 'http://fachhaendlersuche.eurobaustoff.de/i-m/?type=640';

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();

        $subtitle       = 'EUROBAUSTOFF / i&M Bauzentrum';

        if (!$sPage->open($this->_baseUrl)) {
            throw new Exception('couldn\'t open store list url');
        }

        $page = $sPage->getPage()->getResponseBody();
        $sAddress = new Marktjagd_Service_Text_Address();

        $cStore = new Marktjagd_Collection_Api_Store();

        $patternStoreList = '#<ul[^>]*id="storeLocatorResults"[^>]*>(.*?)</ul>#s';
        if (!preg_match($patternStoreList, $page, $matchStoreList)) {
            throw new Exception('Couldn\'t find store list for company '
                . $companyId . ', url: ' . $this->_baseUrl);
        }

        $patternStoreListElements = '#<li[^>]*id="store(.*?)"[^>]*data-lat="(.*?)"[^>]*data-lng="(.*?)"[^>]*>(.*?)</li>#s';
        if (!preg_match_all($patternStoreListElements, $matchStoreList[1], $matchStoreListElements)) {
            throw new Exception('Couldn\'t find match store elements for company '
                . $companyId . ', url: ' . $this->_baseUrl);
        }

        foreach ($matchStoreListElements[4] as $key => $storeElement) {
            $logger->log('crawl store ' . ($key + 1) . ' of ' . count($matchStoreListElements[4]), Zend_Log::INFO);

            $apiStore = new Marktjagd_Entity_Api_Store();
            $patternTitle = '#<h4>\s*(.*?)\s*</h4>#s';

            $apiStore->setSubtitle($subtitle);

            if ('' != trim($matchStoreListElements[1][$key])) {
                $apiStore->setStoreNumber(trim($matchStoreListElements[1][$key]));
            } else {
                $logger->log('Couldn\'t get store number for company ' . $companyId
                    . ', string: ' . $storeElement, Zend_Log::ERR);
                continue;
            }

            if ('' != trim($matchStoreListElements[2][$key])) {
                $apiStore->setLatitude(trim($matchStoreListElements[2][$key]));
            }

            if ('' != trim($matchStoreListElements[3][$key])) {
                $apiStore->setLongitude(trim($matchStoreListElements[3][$key]));
            }

            if (preg_match($patternTitle, $storeElement, $matchTitle)) {
                $apiStore->setTitle($matchTitle[1]);
            } else {
                $logger->log('Couldn\'t match store title for company ' . $companyId
                    . ', string: ' . $storeElement, Zend_Log::ERR);
                continue;
            }

            $patternAddress = '#<span[^>]*class="address"[^>]*>(.*?)</span>#s';
            if (preg_match($patternAddress, $storeElement, $matchAddress)) {
                $address = strip_tags($matchAddress[1]);
                $street = $sAddress->extractAddressPart('street', $address);
                $streetNr = $sAddress->extractAddressPart('streetnumber', $address);
                $apiStore->setStreet($street);
                $apiStore->setStreetNumber($streetNr);
            } else {
                $logger->log('Couldn\'t match street for company ' . $companyId
                    . ', string: ' . $storeElement, Zend_Log::ERR);
                continue;
            }


            $patternCity = '#<span[^>]*class="city"[^>]*>(.*?)</span>#s';
            if (preg_match($patternCity, $storeElement, $matchCity)) {
                $address = strip_tags($matchCity[1]);
                $zip = $sAddress->extractAddressPart('zip', $address);
                $city = $sAddress->extractAddressPart('city', $address);
                $apiStore->setZipcode($zip);
                $apiStore->setCity($city);
            } else {
                $logger->log('Couldn\'t match city for company ' . $companyId
                    . ', string: ' . $storeElement, Zend_Log::ERR);
                continue;
            }


            $patternLogo = '#<span[^>]*class="storeLogo"[^>]*>\s*<img[^>]*src="(.*?)"[^>]*>#s';
            if (preg_match($patternLogo, $storeElement, $matchLogo)) {
                $apiStore->setLogo($matchLogo[1]);
            } else {
                $logger->log('Couldn\'t match logo for company ' . $companyId
                    . ', string: ' . $storeElement, Zend_Log::INFO);
                continue;
            }

            $patternDetailUrl = '#<a[^>]*href="(.*?)"[^>]*>Fachhändler\s*wählen</a>#s';
            if (preg_match($patternDetailUrl, $storeElement, $matchDetailUrl)) {
                $sPage->open($matchDetailUrl[1]);
                $detailPage = $sPage->getPage()->getResponseBody();

                $patternPhone = '#<strong>\s*Telefon\s*:\s*</strong>\s*(.*?)\s*<br[^>]*>#s';
                if (preg_match($patternPhone, $detailPage, $matchPhone)) {
                    $apiStore->setPhone($sAddress->normalizePhoneNumber($matchPhone[1]));
                }

                $patternFax = '#<strong>\s*Telefax\s*:\s*</strong>\s*(.*?)\s*<br[^>]*>#s';
                if (preg_match($patternFax, $detailPage, $matchFax)) {
                    $apiStore->setFax($sAddress->normalizePhoneNumber($matchFax[1]));
                }

                $patternWebsite = '#<strong>\s*Website\s*:\s*</strong>\s*<a[^>]*href="(.*?)"[^>]*>.*?</a>#s';
                if (preg_match($patternWebsite, $detailPage, $matchWebsite)) {
                    $apiStore->setWebsite($matchWebsite[1]);
                }

            } else {
                $logger->log('Couldn\'t match detail-url for company ' . $companyId
                    . ', string: ' . $storeElement, Zend_Log::WARN);
                continue;
            }

            if ($apiStore->getZipcode() == '75365') {
                $apiStore->setTitle('Kömpf Baumarkt GmbH');
            }

            $cStore->addElement($apiStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);

        $crawlerResponse = new Crawler_Generic_Response();
        if ($fileName) {
            $crawlerResponse->setFileName($fileName)
                            ->setIsImport(true)
                            ->setLoggingCode(Crawler_Generic_Response::SUCCESS);
        } else {
            $crawlerResponse->setIsImport(false)
                            ->setLoggingCode(Crawler_Generic_Response::FAILED);
        }

        return $crawlerResponse;
    }
}