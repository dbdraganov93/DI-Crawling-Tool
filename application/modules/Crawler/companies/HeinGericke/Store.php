<<<<<<< .mine
<?php

/**
 * Storecrawler für Hein Gericke (ID: 28619)
 */
class Crawler_Company_HeinGericke_Store extends Crawler_Generic_Company {

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        
        $cStores = new Marktjagd_Collection_Api_Store();
        $mjAddress = new Marktjagd_Service_Text_Address();
        $mjTimes = new Marktjagd_Service_Text_Times();        
        $sPage = new Marktjagd_Service_Input_Page();
        
        $baseUrl = 'http://www.hein-gericke.de';
        $searchUrl = $baseUrl . '/shops/index/list/';        
        
        $resultPages = array($searchUrl);
        
        $nextPage = true;
        do {
            $this->_logger->info('open ' . $resultPages[count($resultPages)-1]);
            $sPage->open($resultPages[count($resultPages)-1]);
            $page = $sPage->getPage()->getResponseBody();
            
            if (preg_match('#<a[^>]*class="[^"]*icon-arrow-right[^"]*"[^>]*href="([^"]+)"#', $page, $match)){
                $resultPages[] = $match[1];
            } else {
                $nextPage = false;
            }
        } while ($nextPage);
        
        $detailPages = array();
        foreach ($resultPages as $resultPage){
            $sPage->open($resultPage);
            $page = $sPage->getPage()->getResponseBody();
            
            if (preg_match_all('#setLocation\(\'(http[^\']+)\'\)#', $page, $match)){
                $detailPages = array_merge($detailPages, $match[1]);
            }            
        }
        
        foreach ($detailPages as $detailPage) {
            $logger->info('open ' . $detailPage);
                    
            if (!$sPage->open($detailPage)) {
                $logger->log($companyId .': unable to open store detail page for store number '
                        . $sStoreId . '.', Zend_Log::ERR);
                continue;
            }

            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#class="street-address"[^>]*>\s*(.+?)\s*<.+?'
                    . 'class="postal-code"[^>]*>\s*(.+?)\s*<.+?'
                    . 'class="locality"[^>]*>\s*(.+?)\s*<.+?'
                    . 'class="tel"[^>]*>(.+?)<.+?href="mailto\:(.+?)"#';

            if (!preg_match($pattern, $page, $aStoreDetailMatches)) {
                $logger->log($companyId . ': unable to get store details for store number '
                        . $sStoreId, Zend_Log::ERR);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore ->setStoreNumber($sStoreId)
                    ->setStreet($mjAddress->extractAddressPart('street', $aStoreDetailMatches[1]))
                    ->setStreetNumber($mjAddress->extractAddressPart('streetnumber', $aStoreDetailMatches[1]))
                    ->setZipcode($aStoreDetailMatches[2])
                    ->setCity($aStoreDetailMatches[3])
                    ->setPhone($mjAddress->normalizePhoneNumber($aStoreDetailMatches[4]))
                    ->setEmail($aStoreDetailMatches[5])
                    ->setWebsite($detailPage);

            $pattern = '#ffnungszeiten\s*</h3>(.+?)</table>#';
            if (preg_match($pattern, $page, $aStoreHourTableMatch)) {                
                $eStore->setStoreHours($mjTimes->generateMjOpenings($aStoreHourTableMatch[1]));
            }

            $pattern = '#<img[^>]*src="(http://www.hein-gericke.de/media/shops/.+?)"#';
            if (preg_match($pattern, $page, $aPicMatches)) {
                $eStore->setImage($aPicMatches[1]);
            }

            $pattern = '#<div[^>]*class="hgshops-shopinfo"[^>]*>.+?</h[^>]*>\s*(.+?)\s*<div#';
            if (preg_match($pattern, $page, $aTextMatch)) {
                //$eStore->setText(trim(strip_tags($aTextMatch[1])));
            }
                        
            if (preg_match('#<div class="address-description">\s*<h3>Parkmöglichkeiten</h3>(.+?)</div>#', $page, $match)){
                $eStore->setParking(trim(strip_tags($match[1])));
            }            

            Zend_Debug::dump($eStore);
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName =$sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}