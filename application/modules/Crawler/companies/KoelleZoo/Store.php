<?php

/* 
 * Store Crawler für Kölle Zoo (ID: 29021)
 */


class Crawler_Company_KoelleZoo_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.koelle-zoo.de';
        $searchUrl = $baseUrl . '/erlebnismaerkte/';

        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        $address = new Marktjagd_Service_Text_Address();


        foreach ($this->getStoreUrls($sPage, $baseUrl, $searchUrl) as $storeDetail) {
            $this->_logger->info("open " . $storeDetail['url']);

            $eStore = new Marktjagd_Entity_Api_Store();

            $adressRaw = $sPage->getDomElsFromUrlByClass($storeDetail['url'], 'ec-white-text')[0];
            if (!preg_match('#Er\s+liegt.*?([A-Z].+?\d+)#', $adressRaw->textContent, $streetAndNr)) {
                $this->metaLog($storeDetail['url'] . " contains no address");
                continue;
            }

            $emailRaw = $sPage->getDomElsFromUrl($storeDetail['url'], 'ec-modal-mail', 'class', 'a');
            $eStore->setEmail($emailRaw[0]->textContent);

            $eStore->setStreetAndStreetNumber($streetAndNr[1]);
            $eStore->setCity($storeDetail['city']);

            $zipCode = $address->getGerZipCode($eStore->getCity(), $eStore->getStreet(), $eStore->getStreetNumber());
            
            if ( ($zipCode) && (strlen($zipCode) > 4)) {
                $eStore->setZipcode($zipCode);
                $eStore->setStoreNumber($eStore->getZipcode());
            }

            $phoneRaw = $sPage->getDomElsFromUrl($storeDetail['url'], 'ec-modal-tel', 'class', 'a');
            $eStore->setPhoneNormalized($phoneRaw[0]->textContent);

            $eStore->setStoreHoursNormalized($this->getOpenings($sPage, $storeDetail['url']));

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * @param $sPage
     * @param $baseUrl
     * @return array
     */
    private function getStoreUrls($sPage, $baseUrl, $searchUrl)
    {
        $storeUrls = [];
        $storeElements = $sPage->getDomElsFromUrlByClass($searchUrl, 'ec-market-slide', 'div', true);

        foreach ($storeElements as $storeElement) {

            $url = $sPage->getDomElsFromDomEl($storeElement, 'ec-market-image')[0]->getAttribute('href');
            $address = $sPage->getDomElsFromDomEl($storeElement, 'ec-market-teaser-wrapper-inner')[0];
            $storeTitle = $address->getElementsByTagName('h3')[0]->textContent;
            $addressLines = preg_split('#Zoo#', $storeTitle);
            $storeCity = trim($addressLines[1]);

            if (strpos($storeCity, '/') !== FALSE) {
                $storeCity = strstr($storeCity, '/', TRUE);
            }
            
            if (preg_match('#^/#', $url)) {
                $url = $baseUrl . $url;
            }
            $storeUrls[] = [
                'url'   => $url,
                'city'  => $storeCity
            ];
        }
        return $storeUrls;
    }

    /**
     * @param $sPage
     * @param $storeDetailUrl
     * @return string
     */
    private function getOpenings($sPage, $storeDetailUrl)
    {
        $sOpenings = '';
        $openingsRaws = $sPage->getDomElsFromUrlByClass($storeDetailUrl, 'ec-opening-details', 'div');
        foreach ($openingsRaws as $openingsRaw) {
            $sOpenings .= trim($openingsRaw->textContent) . ',';
        }
        return $sOpenings;
    }
}