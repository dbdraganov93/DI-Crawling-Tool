<?php

/* 
 * Brochure Crawler für Hagebau (ID: 294)
 */

class Crawler_Company_Hagebau_Brochure extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $aStores = $sApi->findAllStoresForCompany($companyId);
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aStores as $singleStore) {
            if ($singleStore['number'] != '122003') {
                continue;
            }
            $sPage->open($singleStore['homepage']);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#data-iframe="([^"]+?)"#';
            if (!preg_match($pattern, $page, $brochureUrlMatch)) {
                $this->_logger->info($companyId . ' no brochure available: ' . $singleStore['number']);
                continue;
            }
            
            $sPage->open($brochureUrlMatch[1]);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<a\s*href="\/([^"]+?\.pdf)"[^>]*class="gesamt_pdf#';
            if (!preg_match($pattern, $page, $brochureMatch)) {
                $this->_logger->err($companyId . ': unable to find brochure: ' . $brochureUrlMatch[1]);
                continue;
            }
            
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            
            $eBrochure->setUrl('https://beilagen-online.com/' . $brochureMatch[1])
                        ->setTitle('Aktuelle Werbebeilage')
                        ->setStoreNumber($singleStore['number'])
                        ->setTags('Blume, Erde, Werkzeug, Garten, Rasen, Möbel, Laube, Dünger, Pflanze, Grill, Topf, Obst, Gemüse, Mulch')
                        ->setVariety('leaflet');
                
            $cBrochures->addElement($eBrochure, true);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}