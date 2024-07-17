<?php

/**
 * 80003
 */
class Crawler_Company_Offerista_PublisherReformat extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sMail = new Marktjagd_Service_Transfer_Email();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles('./accounting') as $singleRemoteFile) {
            if (preg_match('#template\.csv$#', $singleRemoteFile)) {
                $templateFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
        }

        $sFtp->close();

        $cEmails = $sMail->generateEmailCollection($companyId, 'Publisher Receipts');
        $filePath = APPLICATION_PATH . '/../public/files/tmp/';
        $emailFound = FALSE;

        foreach ($cEmails->getElements() as $eMail) {
            if (preg_match('#Content\-Type\:\s*text\/csv#', $eMail->getText())
                && preg_match('#Content\-Disposition:\s*attachment;#', $eMail->getText())) {
                $pattern = '#Content\-ID:\s*<[^>]*>\s*([^-]+)\s*-#';
                if (!preg_match($pattern, $eMail->getText(), $csvMatch)) {
                    throw new Exception($companyId . ': unable to get csv.');
                }

                $fileName = $filePath . 'publishers.csv';
                $csvContent = base64_decode($csvMatch[1]);
                $fh = fopen($fileName, 'w');
                fwrite($fh, $csvContent);
                fclose($fh);

                if (is_file($fileName)) {
                    $emailFound = $eMail;
                }
            }
        }

        if (!$emailFound) {
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT)
                ->setIsImport(false);

            return $this->_response;
        }

        $aTemplateData = $sPss->readFile($templateFile, TRUE, ',')->getElement(0)->getData();

        foreach ($aTemplateData as $singleRow) {
            $aMatching[$singleRow['text']] = [
                'satzart' => 0,
                'buchsymbol' => 'ER',
                'konto' => $singleRow['konto'],
                'gkonto' => $singleRow['gkonto'],
                'buchcode' => 2,
                'steuercode' => $singleRow['steuercode'],
                'prozent' => 19,
                'kost' => 20200
            ];
        }

        $aDataToReformat = $sPss->readFile($fileName, TRUE, ',')->getElement(0)->getData();
        $aDataToRead = [];
        foreach ($aDataToReformat as $singleRow) {
            $aDataToRead[$singleRow['Accounts Billing Company']][] = $singleRow;
        }

        $aToWrite = [];
        foreach ($aDataToRead as $aInfos) {
            foreach ($aInfos as $singleInfo) {
                if (preg_match('#Schneider\s*Direktmarketing#', $singleInfo['Billing Company'])) {
                    continue;
                }
                $aToWrite[$singleInfo['Billing Company']][] =
                    [
                        'satzart' => 0,
                        'buchsymbol' => 'ER',
                        'konto' => $aMatching[$singleInfo['Billing Company']]['konto'],
                        'gkonto' => $aMatching[$singleInfo['Billing Company']]['gkonto'],
                        'buchcode' => 2,
                        'steuercode' => $aMatching[$singleInfo['Billing Company']]['steuercode'],
                        'prozent' => 19,
                        'kost' => 20200,
                        'text' => $singleInfo['Billing Company'],
                        'belegnr' => $singleInfo['Document Name'],
                        'Dokument' => $singleInfo['Document Name'] . '.pdf',
                        'belegdatum' => $singleInfo['Payout at Date'],
                        'Betrag' => '-' . preg_replace('#\.#', '', $singleInfo['Gross Value']),
                        'steuer' => '-' . preg_replace('#\.#', '', $singleInfo['mwst']),
                    ];
            }
        }
        $fh = fopen(APPLICATION_PATH . '/../public/files/tmp/publisher.csv', 'w');
        fputcsv($fh, [
            'satzart',
            'buchsymbol',
            'konto',
            'gkonto',
            'buchcode',
            'steuercode',
            'prozent',
            'kost',
            'text',
            'belegnr',
            'Dokument',
            'belegdatum',
            'Betrag',
            'steuer',
        ], ';');

        foreach ($aToWrite as $aInfos) {
            foreach ($aInfos as $singleRow) {
                fputcsv($fh, $singleRow, ';');
            }
        }
        fclose($fh);

        $sMail->sendMail(
            [
                'to' => 'buchhaltung@offerista.com',
                'from' => 'di@offerista.com',
                'subject' => 'Publisher Receipts - ' . date('m.Y'),
                'attachment' => [
                    APPLICATION_PATH . '/../public/files/tmp/publisher.csv'
                ]
            ]
        );

        $sMail->archiveMail($emailFound);

        $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT)
            ->setIsImport(false);

        return $this->_response;

    }
}