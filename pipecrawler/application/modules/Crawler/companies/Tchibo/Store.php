<?php

/**
 * Storecrawler für Tchibo (ID: 25)
 */
class Crawler_Company_Tchibo_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        # Step 1 - alle Städte finden, in denen Tchibo vertreten ist
        $baseUrl = 'https://www.tchibo.de/';
        $searchUrl = $baseUrl . 'tchibo-deutschland-si1.html';
        $sPage = new Marktjagd_Service_Input_Page(TRUE);
        $sPage->open($searchUrl);
        $citiesPage = $sPage->getPage()->getResponseBody();
        $linkPattern = '#<a[^>]*href="(.*)" class="c-tp-textbutton">#';
        preg_match_all($linkPattern, $citiesPage, $citiList);

        # wir brauchen nur das Teil-Array [1], da hier alle Links stehen
        $citiList = $citiList[1];
        # Link anpassen (da nur relative Links geliefert werden
        for($i = 0; $i< count($citiList); $i++) {
            $citiList[$i] = $baseUrl . substr($citiList[$i], 2, strpos($citiList[$i], "?") - 2);
        }

        # Step 2 - wir suchen in der Städteliste nach Stores und holen uns deren Detail-URLs
        $storeDetailUrls = [];
        foreach($citiList as $singleCityPage) {

 #           if(preg_match("#dresden-loebtau#",$singleCityPage))
 #               Zend_Debug::dump($singleCityPage);
            $sPage->open($singleCityPage);
            $citiesPage = $sPage->getPage()->getResponseBody();
            $linkPattern = '#<a href="(.*)".*\n.*Zur Filiale<\/span>\n<\/a>#';

            preg_match_all($linkPattern, $citiesPage, $citystoreList);
            if(empty($citystoreList[1])) {
                continue;
            }

            $citystoreList = $citystoreList[1];

            $storeDetailUrls = array_merge($storeDetailUrls, $citystoreList);
            $this->_logger->info($singleCityPage . ": added " .count($citystoreList) . " Stores-URLs to list.");
            sleep(1);
        }

        # Link anpassen (da nur relative Links geliefert werden
        for($i = 0; $i< count($storeDetailUrls); $i++) {

            if(empty($storeDetailUrls[$i])) {
                continue;
            }
            $storeDetailUrls[$i] = $baseUrl . substr($storeDetailUrls[$i],2,strpos($storeDetailUrls[$i],"?")-2);
        }

        $this->_logger->info("removing duplicates from Store-URLs");
        $storeDetailUrls = array_unique($storeDetailUrls);
        $this->_logger->info("Found " . count($storeDetailUrls) . " unique stores.");

        # Step 3 - jedes Store-Detailseite wird geöffnet und die Daten extrahiert
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeDetailUrls as $storeDetailUrl) {

            # Per XPath können wir alle Details finden, da diese ein itemprop-Attribut haben
            $page = $sPage->getResponseAsDOM($storeDetailUrl);
            $xpath = new DOMXPath($page);
            #$name = $xpath->query('//h3[@itemprop="name"]')->item(0);
            $aInfos['streetAddress'] = $xpath->query('//span[@itemprop="streetAddress"]')->item(0)->textContent;
            $aInfos['postalCode'] = $xpath->query('//span[@itemprop="postalCode"]')->item(0)->textContent;
            $aInfos['addressLocality'] = $xpath->query('//span[@itemprop="addressLocality"]')->item(0)->textContent;
            # bei den Bildern greifen wir auf img-Tags mit Höher > 400px zu (unter 400px sind die die ganzen Tchibo-Buttons)
            $aInfos['image'] = $xpath->query('//img[@height>400]')->item(0)->attributes->getNamedItem("src")->textContent;
            $cOpeningHours = $xpath->query('//meta[@itemprop="openingHours"]');
            foreach($cOpeningHours as $cOpeningHour)
            {
                $aInfos['storeHours'] =
                    empty($aInfos['storeHours'])? $cOpeningHour->attributes->getNamedItem("content")->textContent:
                        $aInfos['storeHours'] . ", " . $cOpeningHour->attributes->getNamedItem("content")->textContent;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStreetAndStreetNumber($aInfos['streetAddress'])
                ->setZipcode($aInfos['postalCode'])
                ->setCity($aInfos['addressLocality'])
                ->setWebsite($storeDetailUrl)
                ->setStoreHoursNormalized($aInfos['storeHours'])
                ->setImage($baseUrl.$aInfos['image']);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

}
