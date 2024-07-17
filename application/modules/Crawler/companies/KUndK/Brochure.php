<?php

/* 
 * Prospekt Crawler für K+K (ID: 28854)
 */

class Crawler_Company_KUndK_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $newGenTest = true;

        $sTimes = new Marktjagd_Service_Text_Times();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setTitle('Wochenangebote')
            ->setStart($sTimes->findDateForWeekday($sTimes->getWeeksYear(), date('W'), 'Mo'))
            ->setEnd($sTimes->findDateForWeekday($sTimes->getWeeksYear(), date('W'), 'Sa'))
            ->setVariety('leaflet')
            ->setUrl($this->getUrl())
            ->setBrochureNumber($sTimes->getWeeksYear() . 'kw' . date('W'));

        if ($newGenTest) {
            $sGoogleSpreadsheet = new Marktjagd_Service_Output_GoogleSpreadsheetWrite();
            $sGoogleSpreadsheet->addNewGen($eBrochure);
        }
        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }

    /**
     * @return string
     * @throws Exception
     */
    private function getUrl(): string
    {
        $sPage = new Marktjagd_Service_Input_Page();

        $baseUrl = 'https://www.klaas-und-kock.de/';
        $pdfUrls = [
            $baseUrl . 'kataloge/blaetterkatalog/catalogs/blaetterkatalog_kw' . date('W') . '/pdf/complete.pdf',
            $baseUrl . 'kataloge/blaetterkatalog2/catalogs/blaetterkatalog_kw' . date('W') . '/pdf/complete.pdf',
        ];

        foreach ($pdfUrls as $pdfUrl) {
            if ($sPage->checkUrlReachability($pdfUrl)) {
                return $pdfUrl;
            }
        }
        throw new Exception('No reachable pdf url.');
    }
}