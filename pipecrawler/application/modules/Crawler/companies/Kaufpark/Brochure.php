<?php

/*
 * Prospekt Crawler für Rewe Kaufpark (ID: 28977)
 */

class Crawler_Company_Kaufpark_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.rewe-ihr-kaufpark.de/';
        $searchUrl = $baseUrl . 'wp-content/pageflip/kaufpark/files/assets/common/downloads/rewe-ihr-kaufpark.pdf';
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $currentWeek = date('W');

        if (!$sPage->checkUrlReachability($searchUrl)) {
            throw new Exception($companyId . ': no pdf for week: ' . $currentWeek);
        }

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setUrl($searchUrl)
            ->setVisibleStart(date('d.m.Y', $sTimes->getBeginOfWeek($sTimes->getWeeksYear(), $currentWeek)))
            ->setStart(date('d.m.Y', $sTimes->getBeginOfWeek($sTimes->getWeeksYear(), $currentWeek)))
            ->setEnd(date('d.m.Y', $sTimes->getEndOfWeek($sTimes->getWeeksYear(), $currentWeek)))
            ->setVariety('leaflet')
            ->setTitle('Wochen Angebote')
            ->setBrochureNumber('KW' . $currentWeek);

        $cBrochures->addElement($eBrochure);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
