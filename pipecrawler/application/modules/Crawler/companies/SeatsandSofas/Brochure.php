<?php
/**
 * Brochure Crawler für Seats and Sofas (ID: 69990)
 */

class Crawler_Company_SeatsandSofas_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $baseUrl = 'https://www.seatsandsofas.de/';
        $pdfUrl = $baseUrl . 'app/uploads/' . $sTimes->getWeeksYear() . '/' . date('m') . '/' . $sTimes->getWeekNr() . 'D.pdf';

        if (!$sPage->checkUrlReachability($pdfUrl)) {
            throw new Exception($companyId . ': no brochure for this week.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setUrl($pdfUrl)
            ->setTitle('Möbelangebote')
            ->setEnd(date('d.m.Y', strtotime('next saturday')))
            ->setVariety('leaflet')
            ->setBrochureNumber($sTimes->getWeekNr() . '_' . $sTimes->getWeeksYear());

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }
}