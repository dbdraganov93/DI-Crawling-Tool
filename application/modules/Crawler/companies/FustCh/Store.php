<?php

/**
 * Storecrawler für Fust (ID: 72158)
 */
class Crawler_Company_FustCh_Store extends Crawler_Generic_Company
{

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.fust.ch';
        $searchUrl = $baseUrl . '/de/filialensuche.html';
        $storeDetailUrl = $baseUrl . '/myinterfaces/de/fust/products/filialen/[[ID]]/search.html?productTitle=&productNumber=';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="item"[^>]*data\-id="([^"]+)"[^>]*data\-text="([^"]+)"[^>]*data\-lat="([^"]+)"[^>]*data\-lng="([^"]+)"#';
        if (!preg_match_all($pattern, $page, $matchStores)) {
            throw new Exception('can not find any stores for company ' . $companyId);
        }

        foreach ($matchStores[1] as $key => $storeNumber) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($matchStores[1][$key])
                    ->setLatitude($matchStores[3][$key])
                    ->setLongitude($matchStores[4][$key]);

            $detailUrl = str_replace('[[ID]]', $eStore->getStoreNumber(), $storeDetailUrl);
            $sPage->open($detailUrl);
            $page = $sPage->getPage()->getResponseBody();

            if (!preg_match('#<div[^>]*>Dipl[^<]+</div>\s*(.*?)\s*<div#', $page, $matchAddress)) {
                $this->_logger->err('couln\'t match store address for store ' . $detailUrl);
                continue;
            }

            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $matchAddress[1]);
            if (count($aAddress) == 5) {

                $street = preg_replace('#[^\,]+\,#', '', $aAddress[0]);
                $eStore->setStreetAndStreetNumber($street, 'CH')
                        ->setZipcodeAndCity($aAddress[2])
                        ->setSubtitle($aAddress[1]);
            } else if (count($aAddress) == 4) {
                $eStore->setStreetAndStreetNumber($aAddress[0], 'CH')
                        ->setZipcodeAndCity($aAddress[1]);
            } else {
                $this->_logger->err('unknown address format, url: ' . $detailUrl);
                continue;
            }

            if (preg_match('#<div[^>]*>.+?ffnungszeiten</div>\s*<table>(.+?)</table>#', $page, $matchOpenings)) {
                $eStore->setStoreHoursNormalized($matchOpenings[1], 'table');
            }

            if (preg_match('#<img[^>]*class="filialeimg"[^>]*src="(.+?)"#', $page, $matchImg)) {
                $eStore->setImage($baseUrl . $matchImg[1]);
            }

            if ($eStore->getZipcode() == '' || $eStore->getCity() == '') {
                $this->_logger->err('couldn\'t match store address for store ' . $detailUrl);
                continue;
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
