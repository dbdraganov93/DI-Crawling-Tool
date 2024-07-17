<?php

/*
 * Prospekt Crawler für Ernsting's Family (ID: 22133)
 */

class Crawler_Company_Ernstings_BrochureMail extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sTimes = new Marktjagd_Service_Text_Times();
        $scriptPath = APPLICATION_PATH . '/../scripts/mailread.php';
        exec('php -d mbstring.func_overload=0 ' . $scriptPath . ' ' . $companyId . ' Ernstings');
        $cMails = unserialize(file_get_contents(APPLICATION_PATH . '/../public/files/mail/' . $companyId . '/CollectionData.txt'));

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($cMails as $singleMail) {
            if (!preg_grep('#pdf$#', $singleMail->getLocalAttachmentPath())) {
                continue;
            }
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $pattern = '#Content-Type:\s*text\/plain;\s*charset=\"UTF-8\"\s*Content-Transfer-Encoding:\s*base64\s+(.+?)\s*--=_#s';
            if (!preg_match($pattern, $singleMail->getText(), $contentMatch)) {
                $this->_logger->err($companyId . ': unable to get mail text.');
                continue;
            }

            $mailText = preg_replace('#\s{2,}#', ' ', base64_decode($contentMatch[1]));

            $pattern = '#vom\s+(\d+\.\s+[A-Za-z]+?)\.?\s*(-|bis)\s*(\d+\.\s+[A-Za-z]+)\.?\s*#';
            if (!preg_match($pattern, $mailText, $validityMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure validity from mail text.');
                continue;
            }

            foreach ($singleMail->getLocalAttachmentPath() as $singlePdfName => $singlePdfPath) {
                $pattern = '#_([^_]+?)_Einzelseiten#';
                if (preg_match($pattern, $singlePdfName, $titleMatch)) {
                    $localPdfPath = $singlePdfPath;
                    break;
                }
            }

            foreach ($cMails as $singleMail) {
                $pattern = '#' . $titleMatch[1] . '#';
                if (preg_match($pattern, $singleMail->getSubject())) {
                    $sExcel = new Marktjagd_Service_Input_PhpExcel();
                    $localExcelPath = array_values(preg_grep('#xlsx?$#', $singleMail->getLocalAttachmentPath()))[0];

                    $aData = $sExcel->readFile($localExcelPath)->getElement(0)->getData();

                    $aHeader = array();
                    $aDataToUse = array();
                    foreach ($aData as $singleData) {
                        if (!strlen($singleData[4])) {
                            continue;
                        }
                        if (!count($aHeader)) {
                            $aHeader = $singleData;
                            continue;
                        }
                        $aDataToUse[] = array_combine($aHeader, $singleData);
                    }

                    Zend_Debug::dump($aDataToUse);
                    die;
                }
            }

            $eBrochure->setUrl($eBrochure->generatePublicBrochurePath($localPdfPath))
                ->setTitle($titleMatch[1])
                ->setStart(date('d.m.Y', strtotime($validityMatch[1] . $sTimes->getWeeksYear())))
                ->setEnd(date('d.m.Y', strtotime($validityMatch[3] . $sTimes->getWeeksYear())))
                ->setVisibleStart($eBrochure->getStart())
                ->setVariety('leaflet');
        }
    }

}
