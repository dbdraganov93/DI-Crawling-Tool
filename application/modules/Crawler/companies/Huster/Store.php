<?php

/**
 * Storecrawler für Huster (ID: 29083)
 */
class Crawler_Company_Huster_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.huster-getraenke.de';
        $storeFinderUrl = $baseUrl . '/index.php/huster-vor-ort.html';

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sOpenings = new Marktjagd_Service_Text_Times();
        $cStore = new Marktjagd_Collection_Api_Store();

        $sPage->open($storeFinderUrl);
        $page = $sPage->getPage()->getResponseBody();

        $patternStoreLinks = "#<ul[^>]*id=\"menulist_5-splitmenu\"[^>]*>(.+?)</ul>#";
        if (!preg_match($patternStoreLinks, $page, $matchStoreLinks)){
            throw new Exception($companyId . ': cannot find any store links: ' . $storeFinderUrl);
        }

        if (!preg_match_all('#<a[^>]*href="([^"]+)"#', $matchStoreLinks[1], $matchStoreListLinks)){
            throw new Exception($companyId . ': cannot find any store list links: ' . $storeFinderUrl);
        }

        foreach ($matchStoreListLinks[1] as $storeListLink){
            $this->_logger->log('open ' . $baseUrl . $storeListLink, Zend_Log::INFO);
            $sPage->open($baseUrl . $storeListLink);
            $page = $sPage->getPage()->getResponseBody();

            $patternDetail = '#<tr[^>]*class="sectiontableentry[^"]*">.*?<a[^>]*href="([^"]+)".*?</tr>#';
            if (!preg_match_all($patternDetail, $page, $matchDetailLink)){
                $this->_logger->log($companyId . ': cannot find detail link(s): ' . $storeFinderUrl, Zend_Log::ERR);
            }

            foreach ($matchDetailLink[1] as $detailLink){
                $this->_logger->log('open ' . $baseUrl . $detailLink, Zend_Log::INFO);
                $sPage->open($baseUrl . $detailLink);
                $page = $sPage->getPage()->getResponseBody();

                $eStore = new Marktjagd_Entity_Api_Store();

                if (preg_match('#\/([0-9]+)\-[^\/]+\.html#', $detailLink, $match)){
                    $eStore->setStoreNumber($match[1]);
                }

                if (preg_match('#<p[^>]*id="contact-position"[^>]*>(.+?)</p>#', $page, $match)) {
                    $eStore->setText('Kontakt: ' . trim($match[1]));
                }

                if (preg_match('#<[^>]*id="contact-street"[^>]*>(.+?)<#', $page, $match)) {
                    $eStore->setStreet($sAddress->extractAddressPart('street', $match[1]));
                    $eStore->setStreetNumber($sAddress->extractAddressPart('street_number', $match[1]));
                }

                if (preg_match('#<[^>]*id="contact-postcode"[^>]*>(.+?)<#', $page, $match)) {
                    $eStore->setZipcode(trim($match[1]));
                }

                if (preg_match('#<[^>]*id="contact-(suburb|state)"[^>]*>(.+?)<#', $page, $match)) {
                    $eStore->setCity(trim($match[2]));
                }

                if (preg_match('#<[^>]*id="contact-telephone"[^>]*>.*?<div[^>]*>.*?</div>(.+?)<#', $page, $match)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($match[1]));
                }

                if (preg_match('#<div[^>]*>.+?zeiten.*?</div>.*?<div[^>]*class="misc"[^>]*>(.+?)</div>#', $page, $match)) {
                    $eStore->setStoreHours($sOpenings->generateMjOpenings($match[1]));
                }

                $cStore->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}