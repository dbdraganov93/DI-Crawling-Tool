<?php

/* 
 * Prospekt Crawler für B1Baumarkt (ID: 22382)
 */

class Crawler_Company_B1Baumarkt_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sTimes = new Marktjagd_Service_Text_Times();
        $nextWeek = $sTimes->getWeekNr('next');

        $baseUrl = 'https://www.b1-discount.de/';
        $pdfUrl = $baseUrl . 'images/book_new/pages/' . $nextWeek . '_BM.pdf';
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        
        $eBrochure->setTitle('Wochen Angebote')
                ->setStart(date('d.m.Y', strtotime('saturday this week')))
                ->setEnd(date('d.m.Y', strtotime('saturday next week')))
                ->setVariety('leaflet')
                ->setUrl($pdfUrl)
                ->setBrochureNumber($sTimes->getWeeksYear('next'). 'kw' . $nextWeek);
        
        $cBrochures->addElement($eBrochure);

        $this->getResponse($cBrochures, $companyId);
    }
}