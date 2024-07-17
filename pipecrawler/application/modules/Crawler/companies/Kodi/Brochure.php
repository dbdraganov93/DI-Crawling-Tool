<?php

/*
 * Prospekt Crawler für KODi Diskontläden (ID: 63)
 */

class Crawler_Company_Kodi_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.kodi.de/';
        $searchUrl = $baseUrl . 'Prospekte';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $aDates = array(
            date('W'),
            date('W', strtotime('next week')),
            date('W', strtotime('+2 weeks'))
        );

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aDates as $singleDate) {
            $pattern = '#href="(\/\/api[^"]+?kw' . $singleDate . '[^"]+?\.pdf)"#i';
            if (!preg_match($pattern, $page, $pdfMatch)) {
                $this->_logger->info($companyId . ': no pdf for week ' . $singleDate);
                continue;
            }
            $pdfMatch[1] = 'http:' . $pdfMatch[1];

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setUrl($pdfMatch[1])
                ->setStart($sTimes->findDateForWeekday($sTimes->getWeeksYear('next'), $singleDate - 1, 'So'))
                ->setEnd($sTimes->findDateForWeekday($sTimes->getWeeksYear('next'), (int)$singleDate, 'Sa'))
                ->setVisibleStart($eBrochure->getStart())
                ->setVariety('leaflet')
                ->setTitle('Wochen Angebote')
                ->setBrochureNumber('KW' . $singleDate . '_' . date('Y', strtotime('next week')));

            $cBrochures->addElement($eBrochure);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
