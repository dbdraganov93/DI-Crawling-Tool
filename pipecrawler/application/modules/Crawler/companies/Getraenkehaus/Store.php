<?php

/*
 * Store Crawler für Getränkehaus (ID: 69550)
 */

class Crawler_Company_Getraenkehaus_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.getraenkehaus.de/';
        $searchUrl = $baseUrl . 'markt-finden/';
        $sPage = new Marktjagd_Service_Input_Page();

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $aParams = array(
            'zipcode' => '99084',
            'city' => 'Erfurt',
            'radius' => '1000',
            'submit' => 'Filialen+suchen'
        );
        
        $sPage->open($searchUrl, $aParams);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*class="storeBox[^>]*>(.+?)</div#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#>([^<]+?)<[^>]*>(\s*\d{5}[^<]+?)<#';
            if (!preg_match($pattern, $singleStore, $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address.');
                continue;
            }
            
            $pattern = '#fon:?([^<]+?)<#';
            if (preg_match($pattern, $singleStore, $storePhoneMatch)) {
                $eStore->setPhoneNormalized($storePhoneMatch[1]);
                if (!preg_match('#^0#', $eStore->getPhone())) {
                    $eStore->setPhone('0' . $eStore->getPhone());
                }
            }
            
            $pattern = '#ffnungszeiten(.+?)</p#';
            if (!preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $this->_logger->info($companyId . ': unable to get store hours.');
            }
            
            $pattern = '#<img[^>]*src="\/([^"]+?)"[^>]*class="mainStoreImg#';
            if (preg_match($pattern, $singleStore, $storeImageMatch)) {
                $eStore->setImage($baseUrl . $storeImageMatch[1]);
            }
            
            $pattern = '#<strong[^>]*>\s*([^<]+?)\s*</strong#';
            if (preg_match_all($pattern, $singleStore, $storeSectionMatches)) {
                $eStore->setSection(implode(', ', $storeSectionMatches[1]));
            }
            
            $eStore->setAddress($storeAddressMatch[1], $storeAddressMatch[2])
                    ->setStoreHoursNormalized($storeHoursMatch[1]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
