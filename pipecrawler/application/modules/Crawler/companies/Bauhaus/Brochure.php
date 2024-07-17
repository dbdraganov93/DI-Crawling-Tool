<?php

/**
 * Brochure Crawler für Bauhaus (ID: 577)
 */
class Crawler_Company_Bauhaus_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sTimes = new Marktjagd_Service_Text_Times();

        $searchUrl = 'https://www.bauhaus.info/angebote/werbebeilagen';
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $this->_logger->info('Trying to get the URL: ' . $searchUrl);

        $pattern = '#<a\s*href="(?<url>[^"]*)"\s*title="Jetzt\sblättern#';
        if (!preg_match_all($pattern, $page, $urlMatches)) {
            throw new Exception('Could not find any brochure');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($urlMatches['url'] as $urlMatch) {
            $pdfUrl = $urlMatch . '/GetPDF.ashx';
            $localPath = $sHttp->generateLocalDownloadFolder($companyId);
            $localBrochurePath = $sHttp->getRemoteFile($pdfUrl, $localPath);
            if ($localBrochurePath == '') {
                $this->metaLog("Unable to get PDF from URL: $urlMatch");
                continue;
            }

            if (!$validEnd = $this->getEndDate($localBrochurePath, $page, $sTimes->getWeekNr())) {
                $this->metaLog('Unable to find valid end-date in PDF');
                $this->_logger->warn('Unable to find valid end-date in PDF');
                continue;
            }

            Zend_Debug::dump($localBrochurePath);
            Zend_Debug::dump($validEnd);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle('BAUHAUS: Prospekt KW ' . $sTimes->getWeekNr())
                ->setBrochureNumber("$companyId-KW_" . $sTimes->getWeekNr() . '-' . $sTimes->getWeeksYear())
                ->setUrl($localBrochurePath)
                ->setEnd($validEnd)
                ->setVariety('leaflet');

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

    /**
     * @param string $localBrochurePath
     * @return string
     * @throws Exception
     */
    private function getEndDate(string $localBrochurePath, string $page, string $kw): string
    {
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $strText = $sPdf->extractText($localBrochurePath);

        if (!preg_match('#gültig.+?\s*bis\s*(\d{2}.\d{2}.\d{4})#i', $strText, $validEndMatch)) {
            // First check of the validity in the Page
            if (preg_match(
                '#KW ' . $kw . '\s' . date("Y") . '\s*-\s*gültig.+?\s*bis\s*(\d{2}.\d{2}.\d{4})#',
                $page,
                $pageEndMatch
            )) {
                return $pageEndMatch[1];
            }

            // Second check on the validity of the PDF
            if (preg_match('#Gültig für \d{2} Tage nach Erscheinen#', $strText, $shortEndMatch)) {
                if($shortEndMatch[0] == 'Gültig für 14 Tage nach Erscheinen') {
                    $date = (new DateTime('now'));
                    $date->add(new DateInterval('P14D'));

                    return $date->format('d.m.Y');
                }
            }

            return '';
        }

        return $validEndMatch[1];
    }
}
