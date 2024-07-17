<?php

/**
 * Store Crawler für Mr. Chicken (ID: 71793)
 */
class Crawler_Company_MrChicken_Store extends Crawler_Generic_Company {    
    public function crawl($companyId) {
        $baseUrl = 'http://www.mrchicken.de/cms/filialfinder';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<option.+?value=\"([^\"]+?)\"[^>]*>([^<]+?)<#';
        if (!preg_match_all($pattern, $page, $aStoreMatches)) {
            throw new Exception('Company-ID ' . $companyId . ': unable to get store ids.');
        }

        foreach ($aStoreMatches[1] as $key => $sStoreId) {
            $sPage->open($baseUrl . '/index/' . $sStoreId);
            $page = $sPage->getPage()->getResponseBody();

            preg_match('#<div[^>]+?>[^<]+?<br(.+?)karte anzeigen.+?<\/div>#i', $page, $tmp);
            $tmp2 = preg_split('#<br[^>]*>#', preg_replace('#<div[^>]*>#', '', $tmp[1]));

            $sStoreHours = '';
            $eStore = new Marktjagd_Entity_Api_Store();
            foreach ($tmp2 as $key => $value) {
                if (preg_match('#\d{5}\s+#', $value)) {
                    $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $value))
                            ->setCity($sAddress->normalizeCity($sAddress->extractAddressPart('city', $value)))
                            ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $tmp2[$key-1])))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $tmp2[$key-1])));
                }

                if (preg_match('#Tel#i', $value)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($value));
                }

                if (preg_match('#(Mo|Di|Mi|Do|Fr|Sa)#', $value)) {
                    $sStoreHours .= $value;
                }
            }
            $eStore->setStoreHours($sTimes->generateMjOpenings($sStoreHours));
            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
