<?php
/**
 * Brochure Crawler für Spar CH (ID: 72172)
 */

class Crawler_Company_SparCh_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sTime = new Marktjagd_Service_Text_Times();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $searchUrl = 'https://www.spar.ch/angebote/';
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

//      Getting Brochure url in the following format:
//      <a href="https://angebote.spar.ch/spar-angebote-kw-28-2020/" target="_blank" class="button button--block-align button--brand"> Hier klicken und sparen <i class="fa fa-chevron-right pull-right"></i> </a>
        $pattern = '#<a\s*href="([^"]+?)"[^>]+?>\s*hier klicken und sparen#i';
        if (!preg_match_all($pattern, $page, $urlMatches)) {
            throw new Exception('Could not find any brochure');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($urlMatches[1] as $urlMatch) {
            $pdfUrl = $urlMatch . 'GetPDF.ashx';
            $localPath = $sHttp->generateLocalDownloadFolder($companyId);
            $localBrochurePath = $sHttp->getRemoteFile($pdfUrl, $localPath);
            if ($localBrochurePath == '') {
                $this->metaLog("Unable to get PDF from URL: $urlMatch");
                continue;
            }

            $pattern = '#gültig\s*vo[m|n].+?(\d+).(\d+).+?bis.+?(\d+).(\d+)#i';
            if (!preg_match($pattern, $sPdf->extractText($localBrochurePath), $startEnd)) {
                $this->_logger->alert($companyId . ': unable to get brochure validity: ' . $localBrochurePath);
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle('Spar Top-Angebote KW ' . $sTime->getWeekNr())
                ->setBrochureNumber("$companyId-KW_" . $sTime->getWeekNr() . '-' . $sTime->getWeeksYear())
                ->setUrl($localBrochurePath)
                ->setStart($sTime->getDateWithAssumedYear("$startEnd[1].$startEnd[2].", 'd.m.Y'))
                ->setEnd($sTime->getDateWithAssumedYear("$startEnd[3].$startEnd[4].", 'd.m.Y'))
                ->setVariety('leaflet')
                ->setLanguageCode('de');

            $cBrochures->addElement($eBrochure);
        }
        return $this->getResponse($cBrochures, $companyId);
    }

    /**
     * @param string $brochureUrl
     * @param string $companyId
     * @return string
     */
    private function getLocalBrochurePath(string $brochureUrl, string $companyId): string
    {
        $pdfLinks = [
            dirname($brochureUrl) . "/Gesamt_PDF.pdf",
            $brochureUrl . "Gesamt_PDF.pdf"
        ];

        $sHttp = new Marktjagd_Service_Transfer_Http();
        foreach ($pdfLinks as $pdfLink) {
            $localPath = $sHttp->generateLocalDownloadFolder($companyId);
            if ($localBrochurePath = $sHttp->getRemoteFile($pdfLink, $localPath)) {
                return $localBrochurePath;
            }
        }
        return '';
    }
}
