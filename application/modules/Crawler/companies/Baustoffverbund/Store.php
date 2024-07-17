<?php

/*
 * Store Crawler für Baustoffverbund Süd (ID: 71714)
 */

class Crawler_Company_Baustoffverbund_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.baustoffverbund.de/';
        $searchUrl = $baseUrl . 'index.php?id=36';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<select\s*class="mitglieder_select"[^>]*>(.+?)</select#';
        if (!preg_match($pattern, $page, $zipListMatch)) {
            throw new Exception($companyId . ': unable to get zipcode list.');
        }

        $pattern = '#<option\s*value="([0-9]+?)"#';
        if (!preg_match_all($pattern, $zipListMatch[1], $zipMatches)) {
            throw new Exception($companyId . ': unable to get any zipcodes from list.');
        }

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($zipMatches[1] as $singleZipCode) {
            $aParams = array(
                'tx_wemember_pi1[zip]' => $singleZipCode,
                'tx_wemember_pi1[name]' => '',
                'tx_wemember_pi1[submit]' => 'Absenden');
            
            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div\s*class="mitglieder_wrap"[^>]*>(.+?)<br[^>]*>\s*</div>\s*</div>#';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->info($companyId . ': unable to get any stores from: ' . $singleZipCode);
                continue;
            }
            
            foreach ($storeMatches[1] as $singleStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#>([^>]+?)<br[^>]*>\s*([0-9]{5})\s*([^<]+?)<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }
                
                $pattern = '#<b[^>]*>\s*(.+?)\s*</b#';
                if (preg_match($pattern, $singleStore, $titleMatch)) {
                    $eStore->setTitle($titleMatch[1]);
                }
                
                $pattern = '#img\s*src="([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $logoMatch)) {
                    $eStore->setLogo($baseUrl . $logoMatch[1]);
                }
                
                $pattern = '#Telefon:([^<]+?)<#';
                if (preg_match($pattern, $singleStore, $phoneMatch)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
                }
                
                $pattern = '#Fax:([^<]+?)<#';
                if (preg_match($pattern, $singleStore, $faxMatch)) {
                    $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
                }
                
                $pattern = '#>([^<]+?\(at\)[^<]+?)<#';
                if (preg_match($pattern, $singleStore, $mailMatch)) {
                    $eStore->setEmail(preg_replace(array('#\(at\)#', '#\(dot\)#'), array('@', '.'), $mailMatch[1]));
                }
                
                $pattern = '#Homepage:\s*<a\s*href="([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $urlMatch)) {
                    $eStore->setWebsite($urlMatch[1]);
                }
                
                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $addressMatch[1])))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $addressMatch[1])))
                        ->setZipcode($addressMatch[2])
                        ->setCity($addressMatch[3])
                        ->setStoreNumber($eStore->getHash());
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
