<?php

/* 
 * Brochure Crawler für Fristo (ID: 90)
 */

class Crawler_Company_Fristo_Brochure extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.fristo.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        
        $cApiStores = $sApi->findStoresByCompany($companyId);
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($cApiStores->getElements() as $eApiStore) {
            $sPage->open($eApiStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*class="angebot_pdf">\s*<a[^>]*href="([^"]+?\.pdf)"#';
            if (!preg_match($pattern, $page, $brochureUrlMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure url: ' . $eApiStore->getWebsite());
                continue;
            }
            
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            
            $eBrochure->setUrl($baseUrl . $brochureUrlMatch[1])
                    ->setTitle('Wochen Angebote')
                    ->setVariety('leaflet')
                    ->setStoreNumber($eApiStore->getStoreNumber());
            
            $cBrochures->addElement($eBrochure);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        $this->_response->generateResponseByFileName($fileName);
        $this->_response->setIsImport(FALSE);

        return $this->_response;
    }
}