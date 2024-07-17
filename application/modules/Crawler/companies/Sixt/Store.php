<?php

/* 
 * Store Crawler für Sixt (ID: 22229)
 */
class Crawler_Company_Sixt_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $baseUrl = 'http://www.sixt.de/';
        $searchUrl = $baseUrl . 'mietwagen/deutschland/';
        $sPage = new Marktjagd_Service_Input_Page();

        if (!$sPage->open($searchUrl)) {
            $this->_logger->log($companyId . ': unable to open store list page.', Zend_Log::CRIT);
        }

        $page = $sPage->getPage()->getResponseBody();
        $pattern = '#<ul[^>]*list-of-cities[^>]*>(.+?)<\/ul>\s*<\/div>#';
        if (!preg_match($pattern, $page, $listMatch)) {
            $this->_logger->log($companyId . ': unable to get store list.', Zend_Log::CRIT);
        }
        $pattern = '#href=\"\/mietwagen\/deutschland\/(.+?)\"#';
        if (!preg_match_all($pattern, $listMatch[1], $aStoreLinks)) {
            $this->_logger->log($companyId . ': no store links found.', Zend_Log::CRIT);
        }

        $cStore = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreLinks[1] as $singleStoreLink) {
            if (!$sPage->open($searchUrl . $singleStoreLink)) {
                $this->_logger->log($companyId . ': unable to open city page for url: '
                        . $singleStoreLink, Zend_Log::ERR);
                continue;
            }
            $page = $sPage->getPage()->getResponseBody();
            $pattern = '#<li[^>]*class=\"station-(.+?)\">(.+?)</li>#';
            if (!preg_match_all($pattern, $page, $aStoreMatches)) {
                $this->_logger->log($companyId . ': no stores found.', Zend_Log::INFO);
                continue;
            }
            for ($i = 0; $i < count($aStoreMatches[0]); $i++) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $sTimes = new Marktjagd_Service_Text_Times();
                $sAddress = new Marktjagd_Service_Text_Address();

                $pattern = '#(.+?)<table#';
                if (!preg_match($pattern, $aStoreMatches[2][$i], $match)) {
                    $this->_logger->log($companyId . ': no store address found.', Zend_Log::ERR);
                    continue;
                }
                $aAddress = preg_split('#<br[^>]*>#', $match[1]);

                $pattern = '#<table[^>]*>(.+?)</table#';
                if (preg_match($pattern, $aStoreMatches[2][$i], $timeMatch)) {
                    $strTime = $sTimes->generateMjOpenings($timeMatch[1], 'table');
                }

                $eStore->setStoreNumber($aStoreMatches[1][$i])
                        ->setStoreHours($strTime)
                        ->setStreet($sAddress->extractAddressPart('street', $aAddress[1]))
                        ->setStreetNumber($sAddress->extractAddressPart(('streetnumber'), $aAddress[1]))
                        ->setZipcode($sAddress->extractAddressPart('zip', $aAddress[2]))
                        ->setCity($sAddress->extractAddressPart('city', $aAddress[2]));

                $cStore->addElement($eStore, TRUE);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
