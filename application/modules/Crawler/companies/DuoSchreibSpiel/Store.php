<?php

/**
 * Storecrawler für duo schreib & spiel
 *
 * Class Crawler_Company_DuoSchreibSpiel_Store
 */
class Crawler_Company_DuoSchreibSpiel_Store extends Crawler_Generic_Company {

    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $baseUrl = 'http://www.duo-shop.de';
        $count = 1;
        $maxImageCount = 4;

        $servicePage = new Marktjagd_Service_Input_Page();
        $page = $servicePage->getPage();
        $page->setUseCookies(true)
                ->setMethod('POST');
        $servicePage->setPage($page);

        $servicePage->open($baseUrl . '/Notification/Location', array('city' => '01067'));

        $page->setMethod('GET');
        $servicePage->setPage($page);

        $cStore = new Marktjagd_Collection_Api_Store();

        $siteCount = 0;
        $storeCount = 0;
        $patternDealerLogo = '#<span[^>]*class="dealer-logo"[^>]*>.*?<img[^>]*src="(.*?)"[^>]*>.*?</span>#s';
        $patternStoreInfo = '#<h3>\s*(.*?)\s*</h3>\s*<p>\s*(.*?)\s*</p>#s';
        $patternStoreLink = '#<a[^>]*href="(.*?)"[^>]*>\s*(.*?)\s*</a>#s';
        $patternText = '#<div[^>]*class="text"[^>]*>\s*(.*?)\s*</div>#s';
        $patternImage = '#<a[^>]*href="(/de-DE/Document/Image/[^"]*)"[^>]*title="Thumbnail\s*1"[^>]*>.*?</a>#s';

        do {
            $storeListUrl = $baseUrl . '/de-DE/Dealer/List/-/' . $count;
            $this->_logger->log('store list url: ' . $storeListUrl, Zend_Log::INFO);
            $servicePage->open($storeListUrl);
            $sPage = $servicePage->getPage()->getResponseBody();

            $patternStore = '#<tr[^>]*class="[^"]*dealer[^"]*"[^>]*>(.*?)</tr>#s';
            if (preg_match_all($patternStore, $sPage, $matchStores)) {

                $sAddress = new Marktjagd_Service_Text_Address();


                $this->_logger->log('site count: ' . ++$siteCount, Zend_Log::INFO);
                foreach ($matchStores[1] as $matchStore) {
                    $eStore = new Marktjagd_Entity_Api_Store();
                    if (preg_match($patternDealerLogo, $matchStore, $matchLogo)) {
                        $eStore->setLogo($baseUrl . $matchLogo[1]);
                    }

                    if (preg_match($patternStoreInfo, $matchStore, $matchStoreInfo)) {
                        $subtitle = $matchStoreInfo[1];
                        if (preg_match($patternStoreLink, $subtitle, $matchesStoreLink)) {
                            $eStore->setWebsite($baseUrl . $matchesStoreLink[1]);
                            $subtitle = $matchesStoreLink[2];
                        }

                        $eStore->setTitle($subtitle);

                        $aAddress = preg_split('#<br\s*/>#i', $matchStoreInfo[2]);

                        $zipCode = $sAddress->extractAddressPart('zipcode', $aAddress[1]);
                        if (5 != strlen($zipCode)) {
                            $this->_logger->log('not a german store, zipcode: ' . $zipCode, Zend_Log::INFO);
                            continue;
                        }


                        $eStore->setStreet($sAddress->extractAddressPart('street', $aAddress[0]))
                                ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0]))
                                ->setZipcode($zipCode)
                                ->setCity($sAddress->extractAddressPart('city', $aAddress[1]));
                    }

                    if (strlen($eStore->getWebsite())) {
                        $servicePage->open($eStore->getWebsite());
                        $page = $servicePage->getPage()->getResponseBody();
                        if (preg_match($patternText, $page, $matchText)) {
                            $text = $matchText[1];
                            $text = preg_replace('#</li>#i', '<br>', $text);
                            $eStore->setText($text);
                        }

                        if (preg_match_all($patternImage, $page, $matchImages)) {
                            $imageCount = 0;
                            $imageLinks = '';

                            foreach ($matchImages[1] as $imageLink) {
                                if ($imageCount < $maxImageCount) {
                                    if (strlen($imageLinks)) {
                                        $imageLinks .= ', ';
                                    }

                                    $imageLinks .= $baseUrl . $imageLink;
                                    $imageCount++;
                                }
                            }
                            $eStore->setImage($imageLinks);
                        }
                    }
                    
                    if ($eStore->getZipcode() == '01728') {
                        $eStore->setEmail('info@alles-meine.de');
                    }

                    if ($eStore->getZipcode() == 59329 && preg_match('#posskamp#is', $eStore->getStreet())){
                        continue;
                    }                    
                    
                    if ($eStore->getZipcode() == '39307') {
                        $eStore->setStoreHours('Mo-Fr 09:00-18:00, Sa 09:00-13:00');
                    }
                    
                    if (strlen($eStore->getText()) > 999){
                        $eStore->setText(substr($eStore->getText(), 0, 990) . '...');
                    }
                    
                    $cStore->addElement($eStore);

                    $this->_logger->log('store count: ' . ++$storeCount, Zend_Log::INFO);
                }
            }
            $count++;
        } while (preg_match('#<span\s*class="[^"]*next-page[^"]*"#s', $sPage));

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }

}
