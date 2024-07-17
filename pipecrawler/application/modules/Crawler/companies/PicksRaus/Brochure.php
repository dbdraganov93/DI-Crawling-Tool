<?php

/*
 * Brochure Crawler für Picks Raus (ID: 69653)
 */

class Crawler_Company_PicksRaus_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sMail = new Marktjagd_Service_Transfer_Email('PicksRaus');
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTimes = new Marktjagd_Service_Text_Times();

        $aBrochures = $sApi->findActiveBrochuresByCompany($companyId);

        $brochureNeeded = TRUE;
        if (count($aBrochures)) {
            foreach ($aBrochures as $singleBrochure) {
                if (is_array($singleBrochure) && (strtotime('+ 2 day') < strtotime($singleBrochure['validTo']))) {
                    $brochureNeeded = FALSE;
                }
            }
        }

        if (!$brochureNeeded) {
            $this->_response->setIsImport(FALSE)
                ->setLoggingCode(4);

            return $this->_response;
        }

        $week = 'next ';

        $strStoreNumbers = '7,8,11,16,20,34';

        $cMails = $sMail->generateEmailCollection($companyId, 'PicksRaus');

        if (!count($cMails->getElements())) {
            $this->_response->setIsImport(FALSE)
                ->setLoggingCode(3);

            return $this->_response;
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($cMails->getElements() as $eMail) {
            $pattern = '#KW\s*' . date('W', strtotime($week . 'week')) . '#';
            if (!preg_match($pattern, $eMail->getSubject())) {
                continue;
            }
            $patterns = array(
                '#<a[^>]*=[^>]*href=3D"([^"]+?)"#s',
                '#>(https://www.dropbox.com/[^<]*?\.pdf[^<]*)<#',
                '#(https:\/\/www\.dropbox\.com[^\?]+\?[^\s]+)#'
            );
            $found = false;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $eMail->getText(), $urlMatch)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $this->_logger->err($companyId . ': unable to get brochure url from mail text.');
                continue;
            }

            $brochureUrl = preg_replace(array('#=\n|\s+#', '#3D0#'), array('', '1'), $urlMatch[1]);
            $localPath = $sHttp->generateLocalDownloadFolder($companyId);

            $sHttp->getRemoteFile($brochureUrl, $localPath);

            foreach (scandir($localPath) as $singleFile) {
                if (preg_match('#\.pdf$#', $singleFile)) {
                    exec('mv ' . $localPath . $singleFile . ' ' . $localPath . 'Flugblatt_KW' . $sTimes->getWeekNr($week) . '.pdf');
                    $localBrochureFile = $localPath . 'Flugblatt_KW' . date('W', strtotime($week . ' week')) . '.pdf';
                    break;
                }
            }

            foreach ($eMail->getLocalAttachmentPath() as $singleAttachmentFile) {
                $pattern = '#KW\s*' . $sTimes->getWeekNr($week) . '\.xls$#';
                if (preg_match($pattern, $singleAttachmentFile)) {
                    $aData = $sExcel->readFile($singleAttachmentFile, TRUE)->getElement(0)->getData();
                    break;
                }
            }

            $needTrossingen = '';
            foreach ($aData as $singleLine) {
                $pattern = '#Trossingen#';
                foreach ($singleLine as $singleColumn) {
                    if (preg_match($pattern, $singleColumn)) {
                        $needTrossingen = ',45';
                        break 2;
                    }
                }
            }

            $pattern = '#gilt\s*von[^\d]+?(\d{2}\.\d{2}\.\s*\d{4})\s*bis[^\d]+?(\d{2}\.\d{2}\.\s*\d{4})#';
            $text = str_replace("\n", "", $eMail->getText());
            if (!preg_match($pattern, $text, $validityMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure validity.');
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Wochen Angebote')
                ->setUrl($sHttp->generatePublicHttpUrl($localBrochureFile))
                ->setStoreNumber($strStoreNumbers . $needTrossingen)
                ->setStart($validityMatch[1])
                ->setEnd($validityMatch[2])
                ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . ' - 2 day')))
                ->setVariety('leaflet')
                ->setBrochureNumber('KW' . $sTimes->getWeekNr($week) . '_' . $sTimes->getWeeksYear($week));

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
