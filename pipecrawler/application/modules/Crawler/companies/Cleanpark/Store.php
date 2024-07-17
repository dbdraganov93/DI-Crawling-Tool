<?php

/* 
 * Store Crawler für cleanpark (ID: 71781)
 */

class Crawler_Company_Cleanpark_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://bestellsystem.cleanpark.de/';
        $searchUrl = $baseUrl . 'ajax/ajax.main';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();
        
        $aZipcodes = $sDbGeo->findAllZipCodes();
        
        $aParams['option'] = 'pos_organisation_search_update';
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipcodes as $singleZip)
        {
            $aParams['address'] = $singleZip;
            $oPage = $sPage->getPage();
            $oPage->setMethod('POST');
            $sPage->setPage($oPage);
            
            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();
            
            $xmlStores = simplexml_load_string($page, 'SimpleXMLElement', LIBXML_NOCDATA);
            
            $pattern = '#lat\:\s*([^,]+?)\,\s*lng\:\s*([^,]+?)\,[^\{]+?onclick_html\:\s*([^,]+?),#';
            if (!preg_match_all($pattern, $xmlStores->response[1], $storeMatches))
            {
                $this->_logger->info($companyId . ': no stores for zipcode: ' . $singleZip);
                continue;
            }
            
            for ($i = 0; $i < count($storeMatches);$i++)
            {
                $pattern = '#<div>\s*([^<]+?)\s*</div#';
                if (!preg_match_all($pattern, $storeMatches[3][$i], $addressMatches)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $storeMatches[3][$i]);
                    continue;
                }
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $eStore->setLatitude($storeMatches[1][$i])
                        ->setLongitude($storeMatches[2][$i])
                        ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $addressMatches[1][0])))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $addressMatches[1][0])))
                        ->setCity($sAddress->extractAddressPart('city', $addressMatches[1][1]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $addressMatches[1][1]));
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}