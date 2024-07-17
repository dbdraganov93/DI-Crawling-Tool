<?php

/* 
 * Brochure Crawler für Galeria Kaufhof (ID: 20)
 */

class Crawler_Company_GaleriaKaufhof_Brochure extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        
        $aBrochures = $sApi->findActiveBrochuresByCompany($companyId);
        if (count($aBrochures)) {
            $this->_response->setLoggingCode(4)
                    ->setIsImport(FALSE);
            
            return $this->_response;
        }
        
        $baseUrl = 'https://www.galeria-kaufhof.de/';
        $searchUrl = $baseUrl . 'aktuelle-werbung/';
        $sPage = new Marktjagd_Service_Input_Page();
                
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*class="ex-catalogues__download"[^>]*>(.+?)</a#';
        if (!preg_match($pattern, $page, $brochureInfoListMatch)) {
            throw new Exception($companyId . ': unable to get brochure info list.');
        }
        
        $pattern = '#href="\s*([^"]+?)\s*"[^>]*"data":\{"id":"(([^"]+?)-([^-]+?))"#';
        if (!preg_match($pattern, $brochureInfoListMatch[1], $brochureInfoMatch)) {
            throw new Exception($companyId . ': unable to get brochure infos from list.');
        }
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        
        $eBrochure->setTitle($brochureInfoMatch[4])
                ->setBrochureNumber($brochureInfoMatch[2])
                ->setUrl($brochureInfoMatch[1])
                ->setVariety('leaflet')
                ->setStart($brochureInfoMatch[3])
                ->setEnd(date('Y-m-d', strtotime($eBrochure->getStart() . ' + 12 days')));
        
        $cBrochures->addElement($eBrochure);
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}