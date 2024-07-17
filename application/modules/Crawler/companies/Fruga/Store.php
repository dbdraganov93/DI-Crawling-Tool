<?php

class Crawler_Company_Fruga_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.fruga.de/';
        $searchUrl = $baseUrl . 'index.php?option=com_zoo&view=category&layout=category&Itemid=107/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sAddress = new Marktjagd_Service_Text_Address();

        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open store list page.');
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="pos-media[^>]*media-center"[^>]*>.+?item_id=(.+?)&#';

        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ' : unable to get any stores.');
        }

        $cStore = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStoreLink) {
            $searchUrl = $baseUrl . 'index.php?option=com_zoo&task=item&item_id='
                    . $singleStoreLink . '&category_id=1&Itemid=107';
            if (!$sPage->open($searchUrl)) {
                throw new Exception($companyId . ': unable to open store detail page.');
            }
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<div[^>]*class="address"[^>]*>(.+?)<div[^>]*class="pos-bottom"#';
            if (!preg_match($pattern, $page, $match)) {
                throw new Exception($companyId . ': unable to get store details.');
            }
            
            $pattern = '#</strong>([^<].+?)</li#';
            if (!preg_match_all($pattern, $match[1], $addressMatches)) {
                throw new Exception($companyId . ': unable to get store address.');
            }
            
            $eStore->setStoreNumber($singleStoreLink)
                    ->setStreet($sAddress->extractAddressPart('street', $addressMatches[1][0]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $addressMatches[1][0]))
                    ->setZipcode($addressMatches[1][1])
                    ->setCity($addressMatches[1][2])
                    ->setPhone($sAddress->normalizePhoneNumber($addressMatches[1][4]))
                    ->setFax($sAddress->normalizePhoneNumber($addressMatches[1][5]));
            
            $pattern = '#zeiten.+?<p[^>]*>(.+?)</div#';
            if (!preg_match($pattern, $match[1], $timeMatch)) {
                $this->_logger->log($companyId . ': unable to get store hours', Zend_Log::INFO);
            }
            
            $eStore->setStoreHours($sTimes->generateMjOpenings($timeMatch[1]));
            
            $cStore->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
