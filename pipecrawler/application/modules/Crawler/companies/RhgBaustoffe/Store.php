<?php

/*
 * Store Crawler für RHG Baustoffe (ID: 29127)
 */

class Crawler_Company_RhgBaustoffe_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.rhg.eu/';
        $aSearchPattern = array(
            'Baustoffe' => 'baustoffe\/[^\.]+?\.html)',
            'Bau & Garten' => 'bau-garten\/bau-garten-[^\.]+?\.html)',
            'Landhandel' => 'bau-garten\/[^\/]+?\/landhandel\.html)'
        );
        $aSearchUrls = array(
            'Baustoffe' => 'baustoffe',
            'Bau & Garten' => 'bau-garten',
            'Landhandel' => 'landhandel'
        );
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        $aSections = array();

        foreach ($aSearchUrls as $sectionName => $sectionPattern)
        {
            $searchUrl = $baseUrl . 'start/markt-uebersicht/rhg-' . $aSearchUrls[$sectionName] . '.html';
            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a\s*href="\/?(start\/' . $aSearchPattern[$sectionName] . '"\s*onmouseover=#';
            if (!preg_match_all($pattern, $page, $storeMatches))
            {
                throw new Exception($companyId . ': unable to get any store ids.');
            }
            for ($i = 0; $i < count($storeMatches[1]); $i++)
            {
                $aStoreDetailUrls[$sectionName][] = $storeMatches[1][$i];
            }
        }

        foreach ($aStoreDetailUrls as $sectionName => $aUrls)
        {
            foreach ($aUrls as $singleStore)
            {
                $storeSection = $sectionName;
                $storeDetailUrl = $baseUrl . $singleStore;
                $sPage->open(preg_replace('#(\.html)#', '/zoo.html', $storeDetailUrl));
                $info = $sPage->getPage()->getResponseBody();
                if (!preg_match('#<title[^>]*>Seite\s*nicht\s*gefunden\s*</title>#', $info))
                {
                    $storeSection = $sectionName . ', Zoo';
                }

                $sPage->open($storeDetailUrl);
                $page = $sPage->getPage()->getResponseBody();

                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#<p[^>]*>\s*(<[^>]*>)*\s*([^<]+?)\s*(<[^>]*>)*\s*([0-9]{5}[^<]+?)\s*<#';
                if (!preg_match($pattern, $page, $storeAddressMatch))
                {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                    continue;
                }

                $pattern = '#<strong[^>]*>\s*(Öffnungs.+?)?(Mo.+?)Öffnungs#';
                if (preg_match($pattern, $page, $storeHoursMatch))
                {
                    $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[2]));
                }

                $pattern = '#Tel([^<]+?)<#';
                if (preg_match($pattern, $page, $phoneMatch))
                {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
                }

                $pattern = '#Fax([^<]+?)<#';
                if (preg_match($pattern, $page, $faxMatch))
                {
                    $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
                }

                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $storeAddressMatch[2])))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $storeAddressMatch[2])))
                        ->setCity($sAddress->extractAddressPart('city', $storeAddressMatch[4]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $storeAddressMatch[4]))
                        ->setSection($storeSection)
                        ->setStoreNumber($eStore->getHash(true));

                    $aSections[$eStore->getHash(true)][] = $storeSection;
                    
                if (!$cStores->addElement($eStore))
                {
                    $cStores->removeElement($eStore->getHash(true));
                    $aSections[$eStore->getHash(true)] = array_unique($aSections[$eStore->getHash(true)]);
                    $eStore->setSection(implode(', ', $aSections[$eStore->getHash(true)]));
                    $cStores->addElement($eStore);
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
