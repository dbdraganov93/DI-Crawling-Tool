<?php

/**
 * Prospektcrawler für Norma (ID: 106)
 *
 */
class Crawler_Company_Norma_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $aDistributions = array(
            'W' => array(
                'A',
                'De',
                'E',
                'F',
                'K',
                'R',
                'Rb',
                'Roe',
            ),
            'O' => array(
                'B',
                'Dm',
                'Ef',
                'Md',
                'Rs',
            )
        );

        $week = 'next';
        $weekNr = $sTimes->getWeekNr($week);
        $year = $sTimes->getWeeksYear($week);

        $localPath = $sFtp->connect($companyId, TRUE);

        $aFiles = [];
        foreach ($sFtp->listFiles("./$year/") as $singleFolder) {
            if (!preg_match('#kw\s*' . $weekNr . '#i', $singleFolder)) {
                continue;
            }
            $folderToUse = $singleFolder;
            break;
        }

        if(!isset($folderToUse)){
            throw new Exception(
                'Cannot find any PDF for the the week KW' . $weekNr . ' on folder ./' . $year . '/ on our FTP'
            );
        }

        foreach ($sFtp->listFiles($folderToUse) as $singleFile) {
            $this->_logger->info('Downloading file: ' . $singleFile);
            $aFiles[] = $sFtp->downloadFtpToDir($singleFile, $localPath);
        }

        if (!count($aFiles)) {
            throw new Exception("didn't find any files on the ftp");
        }

        $aInfo = array();
        foreach ($aFiles as $singleFile) {
            $pattern = '#KW(\d{1,2})[-|_](Clou)?[0]*(\d{1,2}|Titel)[-|_]?(\d{1,2})?[-|_]([A-Z]+[^-_]*?)[-|_|\.]#';
            if (preg_match($pattern, $singleFile, $distMatch)) {
                $weekMatch = $distMatch[1];
                $distMatch[3] = preg_replace('#Titel#', '1', $distMatch[3]);
                $distMatch[5] = preg_replace(array('#West#', '#Ost#', '#ö#', '#ö#'), array('W', 'O', 'oe', 'oe'), $distMatch[5]);
                if (preg_match_all('#(W|O)#', $distMatch[5], $distMatches)) {
                    foreach ($distMatches[1] as $singleDist) {
                        foreach ($aDistributions[$singleDist] as $singleStore) {
                            $aInfo[$singleStore][$distMatch[3]] = $singleFile;
                            if (strlen($distMatch[4])) {
                                $aInfo[$singleStore][$distMatch[4]] = '';
                            }
                        }
                    }
                }
                if (preg_match_all('#([A-Z]?[a-z]*)#', $distMatch[5], $storeMatches)) {
                    foreach ($storeMatches[1] as $singleStore) {
                        if (strlen($singleStore)) {
                            $aInfo[$singleStore][$distMatch[3]] = $singleFile;
                            if (strlen($distMatch[4])) {
                                $aInfo[$singleStore][$distMatch[4]] = '';
                            }
                        }
                    }
                }
            }
        }
        unset($aInfo['W']);
        unset($aInfo['O']);

        foreach ($aInfo as &$singleDist) {
            ksort($singleDist);
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aInfo as $dist => $pdfs) {
            if (max(array_keys($pdfs)) != count($pdfs)) {
                $this->metaLog('incomplete amount of pdf sites: ' . $dist . '-' . implode(', ', array_keys($pdfs)), 'err');
                continue;
//                throw new Exception($companyId . ': incomplete amount of pdf sites: ' . $dist . '-' . implode(',', array_keys($pdfs)));
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle('Wochen Angebote')
                ->setUrl(($sPdf->merge(array_filter($pdfs), $localPath)))
                ->setVariety('leaflet')
                ->setBrochureNumber("KW$weekMatch" . "_$year$dist")
                ->setStart($sTimes->findDateForWeekday($year, $weekMatch, 'Mo'))
                ->setEnd($sTimes->findDateForWeekday($year, $weekMatch, 'So'))
                ->setVisibleStart($eBrochure->getStart())
                ->setDistribution($dist);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}
