<?php

class Crawler_Company_Nwz_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sEmail = new Marktjagd_Service_Transfer_Email('NWZ');
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $cEmails = $sEmail->generateEmailCollection($companyId);

        if (!$cEmails->getElements()) {
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT)
                ->setIsImport(FALSE);
            return $this->_response;
        }

        foreach ($cEmails->getElements() as $eEmail) {
            foreach ($eEmail->getLocalAttachmentPath() as $singleAttachment) {
                if (preg_match('#\.csv$#', $singleAttachment)) {
                    $localClickoutFile = $singleAttachment;
                } elseif (preg_match('#\.pdf$#', $singleAttachment)) {
                    $localBrochureFile = $singleAttachment;
                }
                $returnAddress = preg_replace('#[^<]+<([^>]+?)>#', '$1', $eEmail->getFromAddress());
            }

            $pdfInfos = $sPdf->getAnnotationInfos($localBrochureFile);

            $height = $pdfInfos[0]->height;
            $width = $pdfInfos[0]->width;

            $aData = $sPss->readFile($localClickoutFile, FALSE, ';')->getElement(0)->getData();

            foreach ($aData as $singleRow) {
                if (!$singleRow[6] || !preg_match('#^https?://#', $singleRow[6])) {
                    continue;
                }

                $aClickouts[] = [
                    'page' => $singleRow[1] - 1,
                    'height' => $height,
                    'width' => $width,
                    'startX' => $singleRow[3] * $width,
                    'endX' => ($singleRow[3] + $singleRow[4]) * $width,
                    'startY' => $height - $singleRow[2] * $height,
                    'endY' => $height - ($singleRow[2] + $singleRow[5]) * $height,
                    'link' => $singleRow[6]
                ];
            }

            $coordFileName = APPLICATION_PATH . '/../public/files/tmp/coordinates_' . preg_replace('#\.pdf#', '', basename($localBrochureFile)) . '.json';

            $fh = fopen($coordFileName, 'w+');
            fwrite($fh, json_encode($aClickouts));
            fclose($fh);

            $fileLinked = $sPdf->setAnnotations($localBrochureFile, $coordFileName);

            if ($fileLinked) {
                $aInfos = [
                    'from' => 'di@offerista.com',
                    'to' => $returnAddress,
                    'subject' => 'Linked file: ' . basename($fileLinked),
                    'attachment' => [$fileLinked]
                ];

                $sEmail->sendMail($aInfos);

                $sEmail->archiveMail($eEmail);
            }
        }

        $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT)
            ->setIsImport(FALSE);

        return $this->_response;
    }
}