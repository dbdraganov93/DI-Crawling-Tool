<?php

/* 
 * Prospekt Crawler für NKD (ID: 73284)
 */

class Crawler_Company_NkdAt_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www2.nkd.com/';
        $aPdfWeeks = [
            [
                'startWeek' => date('W'),
                'endWeek' => date('W', strtotime('+1 week')),
            ]
        ];

        $sTimes = new Marktjagd_Service_Text_Times();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aPdfWeeks as $pdfWeek) {
            $pdfUrl = $baseUrl . 'blaetterkatalog/' . date('Y') . 'kw'
                . $pdfWeek['startWeek'] . $pdfWeek['endWeek']
                . '/blaetterkatalog/pdf/complete.pdf';
            $this->_logger->info($companyId . ': trying to get ' . $pdfUrl);
            $localFilePath = $sHttp->getRemoteFile($pdfUrl, $localPath);
            $fileType = exec('file ' . $localFilePath);

            if (!preg_match('#PDF\s*document#', $fileType)) {
                $this->_logger->warn('PDF document was not found in the URL: ' . $fileType);

                // Page got redirect to HTML so by assumption there is no brochure
                if(preg_match('#HTML\s*document#', $fileType)) {
                    $this->_logger->warn('No brochures were found or no need to be imported');
                    $this->_response->setIsImport(false);
                    $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);

                    return $this->_response;
                }

                continue;
            }

            $pdfText = json_decode($sPdf->extractText($localFilePath));
            foreach ($pdfText as $singlePage) {
                if ($singlePage->page == 0 && preg_match('#ab\s*[^,]+?\s*,\s*(\d{2}\.\d{2}\.)\\n#', $singlePage->text, $validStartMatch)) {
                    $startDate = $validStartMatch[1] . date('Y');
                    break;
                }
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Wochen Angebote')
                ->setStart($startDate)
                ->setVariety('leaflet')
                ->setUrl($sHttp->generatePublicHttpUrl($localFilePath))
                ->setBrochureNumber($sTimes->getWeeksYear() . 'kw' . $pdfWeek['startWeek'] . $pdfWeek['endWeek']);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}
