<?php

/**
 * Store Crawler für Herzog und Bräuer (ID: 67728)
 */
class Crawler_Company_HerzogUndBraeuer_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.herzogundbraeuer.de/';
        $searchUrl = $baseUrl . 'company/filialen/branchs.html'
                . '?filter%5Bsearch%5D='
                . '&filter%5Blatitude%5D='
                . '&filter%5Blongitude%5D='
                . '&option=com_branches'
                . '&task=search'
                . '&limit=0'
                . '&limitstart=0'
                . '&task='
                . '&boxchecked=0';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        /*
        $oPage = $sPage->getPage();        
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);        
        $sPage->open($searchUrl, array(
            'filter[search]' => '',
            'limit' => '0',
            'limitstart' => '0')
        ); 
        */
         
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#href="\/(company\/filialen\/branch[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeLinkMatches)) {
            throw new Exception($companyId . ': unable to get any store link.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinkMatches[1] as $singleLink) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $storeLink = $baseUrl . $singleLink;            
            $oPage = $sPage->getPage();
            $oPage->setMethod('GET');
            $sPage->setPage($oPage);
            $sPage->open($storeLink);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<h3[^>]*>\s*Adresse\s*</h3>\s*<p[^>]*>\s*(.+?)\s*</p#s';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->info($companyId . ': unable to get store address for ' . $storeLink);
                continue;
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $addressMatch[1]);
            $aAddress[1] = preg_replace('#\/\s+#', '', $aAddress[1]);
            
            $pattern = '#<h3[^>]*>\s*Telefon\s*</h3>\s*<p[^>]*>\s*(.+?)\s*</p#s';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }
            
            $pattern = '#<h3[^>]*>\s*Öffnungs(.+?)\s*</table#s';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $eStore->setStreet($sAddress->extractAddressPart('street', $aAddress[0]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]));
                        
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
