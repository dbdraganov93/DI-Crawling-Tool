<?php

/*
 * Store Crawler für Landbäckerei Ihle (ID: 68944)
 */

class Crawler_Company_Ihle_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.ihle.de/';
        $searchUrl = $baseUrl . 'alle-filialen.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<p[^>]*>\s*<strong[^>]*>\s*(.+?)\s*</p#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore)
        {
            $eStore = new Marktjagd_Entity_Api_Store();
            $aInfos = preg_split('#\s*(<[^>]*>)+\s*#', $singleStore);
            $aAddress = preg_split('#\s*,\s*#', $aInfos[2]);
//            Zend_Debug::dump($aInfos);die;
            $eStore->setSubtitle($aInfos[0])
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[0])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0])))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setPhone($sAddress->normalizePhoneNumber($aInfos[3]))
                    ->setStoreHours($sTimes->generateMjOpenings($aInfos[4]));
            Zend_Debug::dump($eStore);
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}