<?php

/*
 * Prospekt-Crawler für MäcGeiz (ID: 351)
 */

class Crawler_Company_MaecGeiz_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.mac-geiz.de/';
        $searchUrl = $baseUrl . 'media/offers/KW' . date('W', strtotime('+1 weeks'))
                . '/catalogs/KW' . date('W', strtotime('+1 weeks')) . '/pdf/complete.pdf';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        if (!$sPage->checkUrlReachability($searchUrl)) {
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
            
            return $this->_response;
        }
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        
        $eBrochure->setTitle('Wochen Angebote')
                ->setBrochureNumber('Wochenangebote_KW' . date('W', strtotime('+1 weeks')))
                ->setStart($sTimes->findDateForWeekday($sTimes->getWeeksYear('next'), date('W', strtotime('+1 weeks')), 'Mo'))
                ->setEnd($sTimes->findDateForWeekday($sTimes->getWeeksYear('next'), date('W', strtotime('+1 weeks')), 'Sa'))
                ->setVisibleStart($sTimes->findDateForWeekday($sTimes->getWeeksYear(), date('W') , 'Mi'))
                ->setVariety('leaflet')
                ->setUrl($searchUrl);
        
        $cBrochures->addElement($eBrochure);
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
