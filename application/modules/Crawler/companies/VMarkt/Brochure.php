<?php

/*
 * Brochure Crawler für V-Markt (ID: 1)
 */

class Crawler_Company_VMarkt_Brochure extends Crawler_Generic_Company
{
    private const DISTRIBUTIONS = [
        'schwaben' => 'Schwaben/Oberbayern',
        'muenchen' => 'München',
        'mainburg' => 'Mainburg/Hallertau'
    ];

    public function crawl($companyId)
    {
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $baseURL = 'https://www.v-markt.de';
        $baseRegionURL = '/aktuelles/angebote/';
        $searchURL = $baseURL . '/angebote';

        $pageResponse = $this->curlCipher($searchURL);

        if (!$pageResponse) {
            throw new Exception('The curl responded false and cannot get: ' . $searchURL);
        }

        if (preg_match_all('#<a [^>]*href=\"\/aktuelles\/angebote\/(?<city>[^"]*)#', $pageResponse, $regionMatches)) {
            $this->_logger->info('The crawler managed to get ' . count($regionMatches['city']) . ' regions from the search URL');
        } else {
            throw new Exception('Cannot find any regions that uses /aktuelles/angebote/<city> in ' . $searchURL);
        }

        $localFolder = $sHttp->generateLocalDownloadFolder($companyId);
        $this->_logger->info('Local folder generated at local: ' . $localFolder);

        foreach ($regionMatches['city'] as $region) {
            $this->_logger->info('Working on the URL from region: ' . $region);
            if (!array_key_exists($region, self::DISTRIBUTIONS)) {
                $this->_logger->alert('No distribution found for ' . $region);
            }

            $pageResponse = $this->curlCipher($baseURL . $baseRegionURL . $region);

            $domPage = $this->createDOMDocument($pageResponse);
            $xpath = new DOMXPath($domPage);

            // Get each brochure in first column (AKTUELLE)
            $itemsPattern = '//html/body/div[1]/section[3]/div/div/div[contains(@class, column-center)]';
            $xpathQuery = $xpath->query($itemsPattern);
            foreach ($xpathQuery as $elementNode) {
                /** @var DOMElement $elementNode */
                if (!preg_match('#V-Markt#', $elementNode->textContent) ||
                    preg_match('#Metzgerei#', $elementNode->textContent)
                ) {
                    $this->_logger->info('Skipping one not V-Markt brochure');
                    continue;
                }

                $hrefQuery = $this->getHref($xpath, $elementNode);

                // get brochure name and number
                $nameNumberPattern = '#(?<pdfName>\d{4}_([^\/]*))#';
                if (!preg_match($nameNumberPattern, $hrefQuery, $brochureName)){
                    $this->_logger->warn('No brochure name was found in ' . $hrefQuery);
                }

                // get brochure validity
                $startEndPattern = '#(?<start>\d{2}.\d{2}.)\s*-\s*(?<end>\d{2}.\d{2}.\d{4})#';
                if (preg_match($startEndPattern, $elementNode->textContent, $brochureStartEnd)) {
                    $this->_logger->info('Brochure Start and End found for: ' . $hrefQuery);
                } else {
                    $this->_logger->alert(
                        'Skipping one Brochure - No start or end was found in the string: ' . $elementNode->textContent
                    );
                    continue;
                }

                $pageFlipPath = $this->generatePageFlipPath($region, $hrefQuery);

                $this->_logger->info('Downloading PDF from URL: ' . $pageFlipPath);
                $brochure = $sHttp->getRemoteFile($pageFlipPath, $localFolder, $brochureName['pdfName'] . '.pdf', true);

                $startDate = $brochureStartEnd['start'] . '2021';

                $eBrochure = new Marktjagd_Entity_Api_Brochure();
                $eBrochure->setUrl($brochure)
                    ->setTitle($brochureName['pdfName'])
                    ->setBrochureNumber($brochureName['pdfName'])
                    ->setStart($startDate)
                    ->setVisibleStart($startDate)
                    ->setEnd($brochureStartEnd['end'])
                    ->setVariety('leaflet')
                    ->setDistribution(self::DISTRIBUTIONS[$region])
                ;

                $cBrochures->addElement($eBrochure);
            }

            $xpath = new DOMXPath($domPage);

            // Get each brochure in second column (Vorschau)
            $secondItemsPattern = '//html/body/div[1]/section[5]/div/div/div[contains(@class, column-center)]';
            $xpathQueryPreview = $xpath->query($secondItemsPattern);
            foreach ($xpathQueryPreview as $elementNodePreview) {
                /** @var DOMElement $elementNodePreview */
                if (!preg_match('#V-Markt#', $elementNodePreview->textContent) ||
                    preg_match('#Metzgerei#', $elementNodePreview->textContent)
                ) {
                    $this->_logger->info('Skipping one not V-Markt brochure');
                    continue;
                }

                $hrefQuery = $this->getHref($xpath, $elementNodePreview);

                // get brochure name and number
                $nameNumberPattern = '#(?<pdfName>\d{4}_([^\/]*))#';
                if (!preg_match($nameNumberPattern, $hrefQuery, $brochureName)){
                    $this->_logger->warn('No brochure name was found in ' . $hrefQuery);
                }

                // get brochure validity
                $startEndPattern = '#(?<start>\d{2}.\d{2}.)\s*-\s*(?<end>\d{2}.\d{2}.\d{4})#';
                if (preg_match($startEndPattern, $elementNodePreview->textContent, $brochureStartEnd)) {
                    $this->_logger->info('Brochure Start and End found for: ' . $hrefQuery);
                } else {
                    $this->_logger->alert(
                        'Skipping one Brochure - No start or end was found in the string: ' . $elementNodePreview->textContent
                    );
                    continue;
                }

                $pageFlipPath = $this->generatePageFlipPath($region, $hrefQuery);

                $this->_logger->info('Downloading PDF from URL: ' . $pageFlipPath);
                $brochure = $sHttp->getRemoteFile($pageFlipPath, $localFolder, $brochureName['pdfName'] . '.pdf', true);

                $startDate = $brochureStartEnd['start'] . '2021';

                $eBrochure = new Marktjagd_Entity_Api_Brochure();
                $eBrochure->setUrl($brochure)
                    ->setTitle($brochureName['pdfName'])
                    ->setBrochureNumber($brochureName['pdfName'])
                    ->setStart($startDate)
                    ->setVisibleStart($startDate)
                    ->setEnd($brochureStartEnd['end'])
                    ->setVariety('leaflet')
                    ->setDistribution(self::DISTRIBUTIONS[$region])
                ;

                $cBrochures->addElement($eBrochure);
            }
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    private function getHref(DOMXPath $xpath, DOMElement $elementNode)
    {
        return $xpath->query(
            '/' . $elementNode->getNodePath() . '/center/a/@href'
        )->item(0)->textContent;
    }

    private function generatePageFlipPath(string $region, string $hrefQuery): string
    {
        if ($region == 'muenchen') {
            return $hrefQuery . 'files/assets/common/downloads/V-Markt%20München.pdf';
        }

        return $hrefQuery . 'files/assets/common/downloads/V-Markt%20' . ucfirst($region) . '.pdf';
    }

    private function curlCipher(string $searchURL): string
    {
        $curl = curl_init($searchURL);

        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, 'DEFAULT@SECLEVEL=1');

        $pageResponse = curl_exec($curl);

        curl_close($curl);

        if (!$pageResponse) {
            throw new Exception('The curl responded false and cannot get: ' . $searchURL);
        }

        return $pageResponse;
    }

    private function createDOMDocument(string $page): DOMDocument
    {
        $doc = new DOMDocument();
        $doc->validateOnParse = true;
        $libxml_previous_state = libxml_use_internal_errors(true);
        $doc->loadHTML(mb_convert_encoding($page, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        libxml_use_internal_errors($libxml_previous_state);

        return $doc;
    }
}
