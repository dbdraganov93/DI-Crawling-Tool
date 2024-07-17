<?php

/* 
 * Brochure Crawler für Dänisches Bettenlager (ID: 184)
 */

class Crawler_Company_DaenischesBettenlager_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://viewer.ipaper.io/';
        $searchUrl = $baseUrl . 'danisches-bettenlager/dede/';
        $pdfPath = APPLICATION_PATH . '/../public/files/tmp/tmp.pdf';
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTimes = new Marktjagd_Service_Text_Times();

        $aApiBrochures = $sApi->findActiveBrochuresByCompany($companyId);

        $fh = fopen($pdfPath, 'w+');
        $ch = curl_init($searchUrl . 'GetPDF.ashx');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FILE, $fh);
        curl_exec($ch);
        curl_close($ch);
        fclose($fh);

        $sPdf = new Marktjagd_Service_Output_Pdf();
        $strText = $sPdf->extractText($pdfPath);
        $pattern = '#ltig\s*vom\s*(\d{2}\.\d{2}\.)(\d{4})?\s*bis\s*(\d{2}\.\d{2}\.\d{4})#i';
        if (!preg_match($pattern, $strText, $validityMatch)) {
            throw new Exception($companyId . ': unable to get brochure validity.');
        }

        if (!strlen($validityMatch[2])) {
            $validityMatch[2] = $sTimes->getWeeksYear();
        }

        $validityMatch[1] .= $validityMatch[2];

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        foreach ($aApiBrochures as $singleApiBrochure) {
            if (strtotime($singleApiBrochure['validTo']) == $validityMatch[3]
                && strtotime($singleApiBrochure['validFrom']) == $validityMatch[2]) {
                $this->_logger->info($companyId . ': valid brochure already integrated. skipping run...');
                $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);

                return $this->_response;
            }
        }

        $eBrochure->setTitle('Wochenangebote')
            ->setUrl($pdfPath)
            ->setStart($validityMatch[1])
            ->setEnd($validityMatch[3])
            ->setVisibleStart($eBrochure->getStart())
            ->setBrochureNumber('KW' . date('W') . '_' . $sTimes->getWeeksYear());

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }
}