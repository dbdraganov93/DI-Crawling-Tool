<?php

/*
 * Prospekt Crawler für Saturn (ID: 16)
 */

class Crawler_Company_Saturn_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $baseUrl = 'http://prospekt.saturn.de/';
        $searchUrl = $baseUrl . 'national/flyer/' . $sTimes->getWeeksYear() . 'kw' . $sTimes->getWeekNr() . '/pages/werbemittel.pdf';

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        if (preg_match('#<title[^>]*>404#', $page)) {
            $this->_response->setIsImport(FALSE)
                    ->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
        }
        else {
            $cBrochures = new Marktjagd_Collection_Api_Brochure();
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Wochen Angebote')
                    ->setStart($sTimes->findDateForWeekday($sTimes->getWeeksYear('last'), $sTimes->getWeekNr('last'), 'Sa'))
                    ->setEnd($sTimes->findDateForWeekday($sTimes->getWeeksYear(), $sTimes->getWeekNr(), 'So'))
                    ->setUrl($searchUrl)
                    ->setBrochureNumber($sTimes->getWeeksYear() . '_' . $sTimes->getWeekNr());

            $cBrochures->addElement($eBrochure);
            
            $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
            $fileName = $sCsv->generateCsvByCollection($cBrochures);
            
            $this->_response->generateResponseByFileName($fileName);
        }
        
        return $this->_response;
    }

}