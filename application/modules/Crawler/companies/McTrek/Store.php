<?php

/**
 * Storecrawler für McTrek (ID: 69909)
 */
class Crawler_Company_McTrek_Store extends Crawler_Generic_Company {

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $baseUrl = 'http://www.mctrek.de';
        $searchUrl = $baseUrl . '/uebermctrek/filialen';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li[^>]*>\s*<a[^>]*href="(/Service/filialen/.+?)"#';
        if (!preg_match_all($pattern, $page, $aStoreLinks)) {
            throw new Exception($companyId . ': unable to get stores from url: ' . $this->_baseUrl);
        }

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aStoreLinks[1] as $sStoreLink) {
            usleep(5000000);
            $storeDetailUrl = $baseUrl . $sStoreLink;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#itemprop="([^"]+?)"[^>]*(content|src)="([^"]+?)"#';
            if (!preg_match_all($pattern, $page, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos: ' . $storeDetailUrl);
                continue;
            }
            $aInfos = array_combine($infoMatches[1], $infoMatches[3]);

            $pattern = '#itemprop="openingHours"\s*datetime="([^"]+?)"#';
            if (preg_match_all($pattern, $page, $storeHoursMatches)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings(implode(', ', $storeHoursMatches[1])));
            }

            $eStore->setWebsite($aInfos['url'])
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aInfos['streetAddress'])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aInfos['streetAddress'])))
                    ->setCity($aInfos['addressLocality'])
                    ->setZipcode($aInfos['postalCode'])
                    ->setPhone($sAddress->normalizePhoneNumber($aInfos['telephone']))
                    ->setFax($sAddress->normalizePhoneNumber($aInfos['faxNumber']))
                    ->setEmail($aInfos['email'])
                    ->setImage($baseUrl . $aInfos['image']);

            if (!strlen($eStore->getStreet())) {
                $pattern = '#>\s*([^>]+?)\s*<br[^>]*>\s*([^>]+?<br[^>]*>)?\s*([0-9]{5}[^<]+?)\s*<#s';
                if (preg_match($pattern, $page, $addressMatch)) {
                    $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $addressMatch[1])))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $addressMatch[1])))
                            ->setCity($sAddress->extractAddressPart('city', $addressMatch[3]))
                            ->setZipcode($sAddress->extractAddressPart('zipcode', $addressMatch[3]))
                            ->setStoreNumber($eStore->getHash());
                }

                $pattern = '#tel\.?:?([0-9\s\.]+?)<#i';
                if (preg_match($pattern, $page, $phoneMatch)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
                }

                $pattern = '#fax\.?:?([0-9\s\.]+?)<#i';
                if (preg_match($pattern, $page, $faxMatch)) {
                    $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
                }

                $pattern = '#"mailto:([^"]+)"#';
                if (preg_match($pattern, $page, $mailMatch)) {
                    $eStore->setEmail($mailMatch[1]);
                }

            }
            $eStore->setStoreNumber($eStore->getHash());
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
