<?php
/**
 * Storecrawler für Tk Maxx (ID: 69837)
 */
class Crawler_Company_TkMaxx_Store extends Crawler_Generic_Company
{
    public function crawl ($companyId)
    {
        $baseUrl = 'http://www.tkmaxx.de';     
        $searchUrl = $baseUrl . '/unsere-filialen';
        $sPage = new Marktjagd_Service_Input_Page();

        $oPage = $sPage->getPage();
        $oPage->setUseCookies(true);
        $sPage->setPage($oPage);
                
        $sPage->open($searchUrl);                
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match('#jQuery\.extend\(Drupal\.settings\,\s*(\{\"basePath.+?)\)\;#', $page, $jsonMatch)) {
            throw new Exception('unable to get stores for company with id ' . $companyId);
        }
        
        $json = json_decode($jsonMatch[1]);

        $storeLinks = array();
        foreach ($json->gmap->auto1map->markers as $node){
            if (preg_match('#href="([^"]+)"#', $node->text, $match)){
                $storeLinks[] = $match[1];
            }
        }       
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinks as $storeLink){
            $storeDetailUrl = $baseUrl . $storeLink;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
        
            $pattern = '#itemprop="streetAddress"[^>]*>\s*([^<]+?)\s*<[^>]*>\s*'
                . '<[^>]*itemprop="addressLocality"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
                        
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#itemprop="telephone"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#itemprop="openingHours"[^>]*datetime="([^"]+?)(\s{2,}|")#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#Abteilungen(\s*<[^>]*>\s*)*([^<]+?)\s*</p#';
            if (preg_match($pattern, $page, $sectionMatch)) {
                $eStore->setSection($sectionMatch[2]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setWebsite($storeDetailUrl);
            
            $cStores->addElement($eStore);                
            
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}