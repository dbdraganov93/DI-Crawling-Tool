<?php
/**
 * Brochure Crawler für Möbelix AT (ID: 73091)
 */

class Crawler_Company_MoebelixAt_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.moebelix.at/';
        $searchUrl = $baseUrl . 'brochures';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<section[^>]*BrochureType[^>]*>(.+?)<\/section>#s';
        if (!preg_match_all($pattern, $page, $brochureSectionMatches)) {
            throw new Exception($companyId . ': unable to get any brochure sections.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochureSectionMatches[1] as $singleBrochureSection) {
            $pattern = '#<article[^>]*>(.+?)<\/article>#';
            if (!preg_match_all($pattern, $singleBrochureSection, $brochureMatches)) {
                $this->_logger->err($companyId . ': unable to get any brochures from section.');
                continue;
            }

            foreach ($brochureMatches[1] as $singleBrochure) {
                $pattern = '#>\s*(\d{2}\.\d{2}\.\d{4})\s*-\s*(\d{2}\.\d{2}\.\d{4})\s*<#';
                if (!preg_match($pattern, $singleBrochure, $validityMatch)) {
                    $this->_logger->err($companyId . ': unable to get brochure validity: ' . $singleBrochure);
                    continue;
                }

                $pattern = '#<a[^>]*href="([^"]+?([^\/]+?)_web\.pdf)"[^>]*title="([^"]+?)"#';
                if (!preg_match($pattern, $singleBrochure, $urlTitleMatch)) {
                    $this->_logger->err($companyId . ': unable to get brochure url or title: ' . $singleBrochure);
                    continue;
                }
                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setUrl($this->getLeafletWithClickout($urlTitleMatch[1], $companyId, $urlTitleMatch[2]))
                    ->setBrochureNumber($urlTitleMatch[2])
                    ->setTitle($urlTitleMatch[3])
                    ->setStart($validityMatch[1])
                    ->setEnd($validityMatch[2])
                    ->setVisibleStart($eBrochure->getStart())
                    ->setVariety('leaflet');

                $cBrochures->addElement($eBrochure);
            }
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    /**
     * @param string $url
     * @param string $companyId
     * @return string
     * @throws Zend_Exception
     */
    private function getLeafletWithClickout(string $url, string $companyId, string $param): string
    {
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $localUrl = $sHttp->getRemoteFile($url, $sHttp->generateLocalDownloadFolder($companyId));
        $coords = [
            [
                'width' => 100,
                'height' => 100,
                'page' => 0,
                'startX' => 45,
                'startY' => 65,
                'endX' => 55,
                'endY' => 75,
                'link' => 'https://www.moebelix.at/?utm_source=wogibtswas.at&utm_medium=coop&utm_campaign=' . $param
            ],
        ];

        return $sPdf->setAnnotations($localUrl, $sPdf->getJsonCoordinatesFile($coords));
    }
}