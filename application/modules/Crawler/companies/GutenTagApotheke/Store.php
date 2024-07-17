<?php

class Crawler_Company_GutenTagApotheke_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.guten-tag-apotheken.de/';
        $searchUrl = $baseUrl . 'partnerapotheken/';
        $sPage = new Marktjagd_Service_Input_Page();

        if (!$sPage->open($searchUrl)) {
            $this->_logger->log($companyId . ': unable to open store list page.', Zend_Log::CRIT);
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<select[^>]*id="select_state"[^>]*>(.+?)</select>#';
        if (!preg_match($pattern, $page, $match)) {
            $this->_logger->log($companyId . ': unable to get federal state list.', Zend_Log::CRIT);
        }

        $pattern = '#<option[^>]*value="(.+?)">.+?</option>#';
        if (!preg_match_all($pattern, $match[1], $stateMatches)) {
            $this->_logger->log($companyId . ': unable to get federal states.', Zend_Log::CRIT);
        }
        $storeLinks = array();
        foreach ($stateMatches[1] as $singleStateSite) {
            if ($singleStateSite == '0' || $singleStateSite == '371') {
                continue;
            }
            if (!$sPage->open($searchUrl . '?state=' . $singleStateSite)) {
                $this->_logger->log($companyId . ': unable to open state list page.', Zend_Log::CRIT);
            }
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#div[^>]*class="tx_staddressmap_addresslist_item"[^>]*>\s*<a[^>]*href="(.+?)"#';
            if (!preg_match_all($pattern, $page, $storeLinkMatches)) {
                $this->_logger->log($companyId . ': unable to get any store links.', Zend_Log::CRIT);
            }
            foreach ($storeLinkMatches[1] as $singleStoreLinkMatches) {
                $storeLinks[] = $singleStoreLinkMatches;
            }
        }

        $cStore = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinks as $singleStoreLink) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $sAddress = new Marktjagd_Service_Text_Address();
            if (!$sPage->open($singleStoreLink)) {
                $this->_logger->log($companyId . ': unable to open store page.', Zend_Log::CRIT);
            }
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*id="contact_details"[^>]*>\s*(.+?)\s*</div>#';
            if (!preg_match($pattern, $page, $detailsMatch)) {
                $this->_logger->log($companyId . ': unable to get store details.', Zend_Log::CRIT);
            }

            $pattern = '#<strong[^>]*>(.+?)</strong>#';
            if (!preg_match_all($pattern, $detailsMatch[1], $storeStreetMatches)) {
                $this->_logger->log($companyId . ': unable to get store address.', Zend_Log::CRIT);
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeStreetMatches[1][1]);
            if ($sAddress->extractAddressPart('zip', $aAddress[1]) == '12345') {
                continue;
            }
            
            $pattern = '#Telefon:\s*(.+?)<#';
            if (!preg_match($pattern, $detailsMatch[1], $phoneMatch)) {
                $this->_logger->log($companyId . ': unable to get phone.', Zend_Log::INFO);
            }
            
            
            $pattern = '#Telefax:\s*(.+?)<#';
            if (preg_match($pattern, $detailsMatch[1], $faxMatch)) {
                $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
            }
            
            $pattern = '#href="(.+?)"#';
            if (!preg_match_all($pattern, $detailsMatch[1], $contactMatches)) {
                $this->_logger->log($companyId . ': unable to get contacts.', Zend_Log::INFO);
            }
            
            $eStore->setStreet($sAddress->extractAddressPart('street', $aAddress[0]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setZipcode(str_pad($sAddress->extractAddressPart('zip', $aAddress[1]), 5, '0', STR_PAD_LEFT))
                    ->setEmail(preg_replace('#mailto:#', '', $contactMatches[1][0]))
                    ->setWebsite(preg_replace('#(http://)(http://)#', '$1', $contactMatches[1][1]))
                    ->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]))
                    ->setStoreNumber($eStore->getHash());
            $cStore->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
