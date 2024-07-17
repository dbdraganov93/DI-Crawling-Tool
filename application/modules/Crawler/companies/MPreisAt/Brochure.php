<?php

/*
 * Brochure Crawler für MPreis AT (ID: 72285)
 */

class Crawler_Company_MPreisAt_Brochure extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.mpreis.at/';
        $searchUrl = $baseUrl . 'angebote/aktuelle-angebote/index.htm';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^>]*href="([^"]+?\.pdf)"[^>]*>\s*Aktuelles\s*Flugblatt\s*für\s*([^<]+?)\s*<#i';
        if (!preg_match_all($pattern, $page, $brochureMatches)) {
            Zend_Debug::dump($page);die;
            throw new Exception ($companyId . ': unable to get any brochures.');
        }
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        for ($i = 0; $i < count($brochureMatches[0]); $i++) {
            if (!preg_match('#(Tirol|Salzburg|Osttirol|Kärnten)#', $brochureMatches[2][$i])) {
                continue;
            }
            
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            
            $eBrochure->setTitle('Wochenangebote')
                    ->setUrl($brochureMatches[1][$i])
                    ->setDistribution($brochureMatches[2][$i])
                    ->setStart(date('d.m.Y', strtotime('monday this week')))
                    ->setEnd(date('d.m.Y', strtotime('sunday this week')))
                    ->setVariety('leaflet')
                    ->setBrochureNumber('KW' . date('W') . '_' . $sTimes->getWeeksYear() . '_' . $brochureMatches[2][$i]);
            
            $cBrochures->addElement($eBrochure);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}