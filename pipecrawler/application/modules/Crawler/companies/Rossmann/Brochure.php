<?php

/*
 * Prospekt - Crawler für Rossmann (ID: 26)
 */

class Crawler_Company_Rossmann_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $baseUrl = 'https://angebote.rossmann.de/';

//        $brochureId = $this->getBrochureID($baseUrl);
        $pdfUrl = $this->getPdfUrl($baseUrl);
        $localFolder = $sHttp->generateLocalDownloadFolder($companyId);
        $localPath = $sHttp->getRemoteFile($pdfUrl, $localFolder);

        $eBrochure->setUrl($localPath)
            ->setStart(date('d.m.Y', strtotime('monday this week')))
            ->setEnd(date('d.m.Y', strtotime('sunday this week')))
            ->setTitle('Rossmann: Wochenangebote')
            ->setBrochureNumber(strtotime('this week') . ': Wochenangebote')
            ->setVariety('leaflet');

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }

    /**
     * @param string $baseUrl
     * @return string
     * @throws Exception
     */
    private function getBrochureID(string $baseUrl): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $infos = curl_exec($ch);
        if (preg_match('#Location: .+?\/(\d{1,})\/#', $infos, $brochureNumberMatch)) {
            return trim($brochureNumberMatch[1]);
        }

        throw new Exception("No Brochure ID found");
    }

    /**
     * @param string $baseUrl
     * @param string $brochureId
     * @return string
     * @throws Exception
     */
    private function getPdfUrl(string $baseUrl): string
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        //https://www.rossmann.de/de/kataloge/angebote/catalogs/2021_kw15_beilage/pdf/complete.pdf
        $baseUrl = 'https://www.rossmann.de/de/kataloge/angebote';
        $pdfBaseUrl = $baseUrl . '/catalogs/' . $sTimes->getWeeksYear() . '_kw' . date('W');

        foreach (['/pdf/complete.pdf', '_beilage/pdf/complete.pdf', '_zwischenbeilage/pdf/complete.pdf'] as $partUrl) {
            if ($sPage->checkUrlReachability($pdfBaseUrl . $partUrl)) {
                return $pdfBaseUrl . $partUrl;
            }
        }
        throw new Exception("No reachable PDF-URL found");
    }
}
