<?php

/**
 * Edeka Group Brochure Crawler
 *
 * Ids: 2
 *
 * Class Crawler_Company_EdekaGroup_Brochure
 */
class Crawler_Company_EdekaGroup_Brochure extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     *
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.edeka.de/';
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $aStores = $sApi->findStoresByCompany($companyId)->getElements();

        for ($counter = 0; $counter <= 9; $counter++) {
            $url = $baseUrl . 'search.xml?'
                // Auswahlparameter für Abfrage
                . 'fl=marktID_tlc%2Cplz_tlc%2Cort_tlc%2Cstrasse_tlc%2Cname_tlc%2C'
                . 'geoLat_doubleField_d%2CgeoLng_doubleField_d%2Ctelefon_tlc%2Cfax_tlc%2C'
                . 'services_tlc%2Coeffnungszeiten_tlc%2ChandzettelUrl_tlc%2CknzUseUrlHomepage_tlc%2C'
                . 'urlHomepage_tlc%2CurlExtern_tlc%2CmarktTypName_tlc%2CmapsBildURL_tlc%2C'
                . 'vertriebsschieneName_tlc%2CvertriebsschieneKey_tlc'
                // restliche Parameter
                . '&hl=false&indent=off&q=indexName%3Ab2c'
                . 'MarktDBIndex%20AND%20plz_tlc%3A'
                . $counter
                . '*%20AND%20kanalKuerzel_tlcm%3Aedeka%20AND%20'
                . 'freigabeVonDatum_longField_l%3A%5B0%20TO%201389999599999%5D%20AND%20'
                . 'freigabeBisDatum_longField_l%3A%5B1389913200000%20TO%20*%5D&rows=1000';

            $aStoreListUrl[] = $url;
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        foreach ($aStoreListUrl as $url) {
            if (!$sPage->open($url)) {
                throw new Exception('unable to get store-list for company with id ' . $companyId);
            }

            $jsonStores = $sPage->getPage()->getResponseAsJson();

            foreach ($jsonStores->response->docs as $jsonStore) {
                if (!array_key_exists($jsonStore->marktID_tlc, $aStores)) {
                    continue;
                }
                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setTitle('Edeka: Wochenangebote')
                    ->setStart($sTimes->findDateForWeekday($sTimes->getWeeksYear(), date('W'), 'Mo'))
                    ->setEnd($sTimes->findDateForWeekday($sTimes->getWeeksYear(), date('W'), 'Sa'))
                    ->setVisibleStart($eBrochure->getStart())
                    ->setVariety('leaflet')
                    ->setUrl(preg_replace('#index\.html#', 'blaetterkatalog/pdf/complete.pdf', $jsonStore->handzettelUrl_tlc))
                    ->setStoreNumber($jsonStore->marktID_tlc);

                $cBrochures->addElement($eBrochure, TRUE);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);

    }

}
