<?php

class Crawler_Company_Jibi_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $baseUrl = 'http://www.jibi.de/';
        $linkMatch = base64_encode(json_encode(array(0 => '#(http\:\/\/www\.tcpdf\.org)#s')));
        $storePdfUrl = 'fileadmin/pdf_mittagstisch_wochenplaende/mittagstisch_';
        $keyWords = 'Jibi, Speise, Menükarte, Speisekarte, Menü, Essen, Mittag, '
                . 'Mittagstisch, Mittagessen, Hackfleisch, Fleisch, Eintopf, Suppe, '
                . 'Vegetarisch, Abendgericht, Appetit, Vorbestellung, Bestellung, Nudel, '
                . 'Nudeltag, Schnitzel, Gemüse, Pommes, Kartoffeln, Braten, Fisch';
        $aStoresApi = new Marktjagd_Service_Input_MarktjagdApi();
        $mjTimes = new Marktjagd_Service_Text_Times();
        $cStores = $aStoresApi->findStoresByCompany($companyId);
        $aCardStores = array();
        $count = 0;

        foreach ($cStores->getElements() as $eStore) {
            $pattern = '#(Siehe Menükarte)#';
            if (preg_match($pattern, $eStore->getText())) {
                $aCardStores[] = $eStore;
            }
        }

        $cPdfs = new Marktjagd_Collection_Api_Brochure();

        foreach ($aCardStores as $cardStore) {
            if (!$sPage->open($cardStore->getWebsite())) {
                $logger->log($companyId . ': unable to open store-detail-page of store  '
                        . $cardStore->getStoreNumber(), Zend_Log::ERR);
            }

            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#id="kw-(.+?)-(.+?)"#';
            if (!(preg_match_all($pattern, $page, $match))) {
                continue;
            }
            $weeks = count($match[0]);
            if ($weeks > 2) {
                $weeks = 2;
            }

            for ($i = 0; $i < $weeks; $i++) {
                $dateYear = $match[1][$i];
                $dateWeek = $match[2][$i];
                if ($dateWeek < date('W')) {
                    $dateYear = $sTimes->getWeeksYear() + 1;
                }
            }

            $fileUrl = $baseUrl . $storePdfUrl . $cardStore->getStoreNumber()
                    . '_' . $dateYear . '-' . $dateWeek . '.pdf';
            $sPage->open($fileUrl);
            $localDirectory = APPLICATION_PATH . '/../public/files/pdf/' . $companyId . '/';
            $keyFile = '~/.ssh/pdftron_private_key';

            if (!is_dir($localDirectory)) {
                mkdir($localDirectory, 0777, true);
            }

            $fileName = 'jibi_' . ++$count . '.pdf';
            $fileNameNew = str_replace('.pdf', '_new.pdf', $fileName);
            $localFileName = $localDirectory . $fileName;

            // Datei runterladen
            exec('curl -o ' . $localFileName . ' ' . $fileUrl);

            // Datei zur Modifikation an PDFTron schicken
            exec('scp -P 2210 -o LogLevel=QUIET -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no '
                    . '-r -i ' . $keyFile // Pfad zum PDFTron Key File
                    . ' ' . $localFileName // lokaler Pfad PDF
                    . ' pdftron@service:/tmp/' . $fileName); // Remote-Pfad PDF
            
            // Script aufrufen
            exec('ssh -p 2210 -o LogLevel=QUIET -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -i '
                    . $keyFile . ' pdftron@service LD_LIBRARY_PATH=.'
                    . ' ./removeLinkData.php /tmp/' . $fileName . ' /tmp/' . $fileNameNew
                    . ' ' . $linkMatch);

            // Datei ins lokale System kopieren
            exec('scp -P 2210 -o LogLevel=QUIET -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no '
                    . '-r -i ' . $keyFile // Pfad zum PDFTron Key File
                    . ' pdftron@service:/tmp/' . $fileNameNew . ' ' // Remote-Pfad PDF
                    . $localFileName // lokaler Pfad PDF
            );

            $localFileNameExport = preg_replace(
                    '#^/.*?/files/pdf/#s', 'https://di-gui.marktjagd.de/files/pdf/', $localFileName);

            $ePdf = new Marktjagd_Entity_Api_Brochure();
            $ePdf->setTitle('Menükarte KW ' . $dateWeek)
                    ->setUrl($localFileNameExport)
                    ->setStoreNumber($cardStore->getStoreNumber())
                    ->setBrochureNumber('menue_' . $cardStore->getStoreNumber() . '_week_'
                            . $dateWeek . '_' . $dateYear)
                    ->setStart(date('Y-m-d', $mjTimes->getBeginOfWeek($dateYear, $dateWeek)))
                    ->setEnd(date('Y-m-d', strtotime('-1 days', $mjTimes->getEndOfWeek($dateYear, $dateWeek))))
                    ->setTags($keyWords);

            $cPdfs->addElement($ePdf);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cPdfs);

        return $this->_response->generateResponseByFileName($fileName);
    }
}