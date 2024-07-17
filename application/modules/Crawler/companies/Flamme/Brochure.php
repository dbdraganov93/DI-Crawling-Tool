<?php

/**
 * Brochure Crawler für Flamme (ID: 73622)
 */
class Crawler_Company_Flamme_Brochure extends Crawler_Generic_Company
{
    private $_companyId = '';

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId): Crawler_Generic_Response
    {
        $brochureUrl = "https://www.flamme.de/angebote-prospekte/aktuelle-prospekte/";
        $this->_companyId = $companyId;

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($this->getPDFs($brochureUrl) as $brochureData) {

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle($brochureData['title'])
                ->setStart(date('d.m.Y', time()))
                ->setEnd((new DateTime('last day of this month'))->format('d.m.Y'))
                ->setUrl($brochureData['url'])
                ->setVariety('leaflet');

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    /**
     * @param string $brochureUrl
     * @return array
     * @throws Exception
     */
    private function getPDFs(string $brochureUrl): array
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $rawPdfUrl = $sPage->getDomElsFromUrlByClass($brochureUrl, 'emotion--wrapper')[0]->getAttribute('data-controllerurl');
        $parsedUrl = parse_url($brochureUrl);

        $ret = [];
        $pdfUrls = $sPage->getUrlsFromUrl("$parsedUrl[scheme]://$parsedUrl[host]$rawPdfUrl", '#\.pdf$#');
        foreach ($pdfUrls as $pdfUrl) {
            $ret[] = [
                'title' => $this->getTitle($pdfUrl),
                'url' => $pdfUrl,
            ];
        }

        return $ret;
    }

    /**
     * @param string $url
     * @return string
     * @throws Exception
     */
    private function isValidPdf(string $url): string
    {
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sTimes = new Marktjagd_Service_Text_Times();

        $localPath = $sHttp->generateLocalDownloadFolder($this->_companyId);
        if (!$localBrochurePath = $sHttp->getRemoteFile($url, $localPath)) {
            return '';
        }
        if (!preg_match('#gültig\s+bis\s+[^\d]{0,25}(\d{2}\.\d{2}\.\d{4})#i', $sPdf->extractText($localBrochurePath), $endDate) ||
            !$sTimes->isDateAhead($endDate[1])) {
            return '';
        }
        return $endDate[1];
    }

    /**
     * @param string $url
     * @return string
     */
    private function getTitle(string $url): string
    {
        if (preg_match('#moebel#', $url)) {
            return 'Sortiment Möbel';
        } elseif (preg_match('#kuechen#', $url)) {
            return 'Küchen & E-Geräte';
        } else {
            return 'Angebote';
        }
    }
}
