<?php

/**
 * Store Crawler für AutoSenger (ID: 71909)
 */
class Crawler_Company_AutoSenger_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $baseUrl = 'http://www.auto-senger.de';
        $searchUrl = $baseUrl . '/fileadmin/map/data.json';
        
        $cStores = new Marktjagd_Collection_Api_Store();                
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sPageDetail = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);            
                
        $json = $sPage->getPage()->getResponseBody();
                
        // remove BOM
        $bom = pack('H*','EFBBBF');
        $json = preg_replace("/^$bom/", '', $json);
                
        $json = json_decode($json);
                        
        foreach ($json as $jStore){           
            foreach ($jStore->marken as $brand){                
                $eStore = new Marktjagd_Entity_Api_Store();

                $name = $jStore->name;

                $eStore->setStreetAndStreetNumber($jStore->street)
                        ->setZipcode($jStore->plz)
                        ->setCity($jStore->ort)
                        ->setPhoneNormalized($jStore->tel)
                        ->setFaxNormalized($jStore->fax)
                        ->setText($jStore->additional)
                        ->setLatitude($jStore->lat)
                        ->setLongitude($jStore->lng)
                        ->setParking('kostenlose Parkplätze');
                
                $eStore->setTitle($name . ' (' . $brand->marke . ')')
                        ->setWebsite($brand->link);                
                 
                $this->_logger->info('open ' . $brand->link);
                $sPageDetail->open($brand->link);
                $page = $sPageDetail->getPage()->getResponseBody();                                               

                if (preg_match('#<h[0-9]>Leistungsangebot</h[0-9]>.*?<div[^>]*>(.+?)</div>#', $page, $match)){
                    if (preg_match_all('#<li[^>]*>(.+?)\,?\s*</li>#', $match[1], $submatch)){
                        $eStore->setService(implode(', ', $submatch[1]));
                    }
                }
                
                if (preg_match('#<div[^>]*csc-textpic-image[^>]*>\s*<a[^>]*>\s*<img[^>]src="([^"]+)"#', $page, $match)){
                    $eStore->setImage($match[1]);
                }              
                
                if (preg_match('#ffnungszeiten(.+?)</div>#', $page, $match)){
                    $match[1] = preg_replace('#>(Service|Schautag|Teile).+?<#is', '><', $match[1]);                    
                    $match[1] = preg_replace('#verkauf:?#is', '', $match[1]);
                                    
                    $eStore->setStoreHoursNormalized(strip_tags($match[1]));
                }

                Zend_Debug::dump($eStore);
                $cStores->addElement($eStore, true);
            }            
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);        
    }
}
