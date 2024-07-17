<?php

/**
 * Store Crawler für Rödel Wolle (ID: 70825)
 */
class Crawler_Company_Roedel_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.wolle-roedel.de/';
        $searchUrl = $baseUrl . 'shop/de/Filialen.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sAddress = new Marktjagd_Service_Text_Address();

        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open store list page.');
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<option[^>]*value="/(shop/de/Filialen/.+?)"[^>]*>#';
        if (!preg_match_all($pattern, $page, $storeLinksMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStore = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinksMatches[1] as $singleStoreLink) {
            $searchUrl = $baseUrl . $singleStoreLink;
            $eStore = new Marktjagd_Entity_Api_Store();

            Zend_Debug::dump(preg_replace('#Ã#', '%C3%83%C2%9F', $searchUrl));
            if (!$sPage->open(preg_replace('#Ã#', '%C3%83%C2%9F', $searchUrl))) {
                throw new Exception($companyId . ': unable to open store details page.');
            }

            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#Adresse(.+?)</div>#';
            if (!preg_match($pattern, $page, $storeDetailsMatch)) {
                $this->_logger->log($companyId . ': unable to get store details for ' . $searchUrl, Zend_Log::ERR);
                continue;
            }
            
            $pattern = '#<p[^>]*>(.+?)</p#';
            if (!preg_match_all($pattern, $storeDetailsMatch[1], $details)) {
                $this->_logger->log($companyId . ': unable to get any store details for ' . $searchUrl, Zend_Log::ERR);
                continue;
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $details[1][0]);
            $aContact = preg_split('#\s*<br[^>]*>\s*#', $details[1][2]);
            
            $pattern = '#mailto\:(.+?)"#';
            if (preg_match($pattern, $aContact[2], $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $pattern = '#Fax(.+)#';
            if (preg_match($pattern, $aContact[1], $faxMatch)) {
                $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
            }
            
            $pattern = '#Tel(.+)#';
            if (preg_match($pattern, $aContact[0], $telMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($telMatch[1]));
            }
            
            $eStore->setStoreHours($sTimes->generateMjOpenings($details[1][1]))
                    ->setStreet($sAddress->extractAddressPart('street', $aAddress[count($aAddress)-2]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[count($aAddress)-2]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[count($aAddress)-1]))
                    ->setZipcode($sAddress->extractAddressPart('zip', $aAddress[count($aAddress)-1]))
                    ->setStoreNumber($eStore->getHash());
            
            $cStore->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
