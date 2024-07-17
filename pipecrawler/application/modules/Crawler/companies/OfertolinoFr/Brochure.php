<?php

/*
 * Prospekt Crawler für Action FR (ID: 80314)
 */

class Crawler_Company_OfertolinoFr_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sTimes = new Marktjagd_Service_Text_Times();
        $sFtp = new Marktjagd_Service_Transfer_Ftp();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $aFtpConfig = array(
            'hostname' => '91.196.127.24',
            'username' => 'ofertfr@static.ofertolino.fr',
            'password' => 'gpQh5w3Y7DPk0iwMCwGr',
            'port' => '21'
        );

        $companySearchPrefix = [
            '80314' => 'action',
            '72305' => 'lidl',
            '72314' => 'e-leclerc',
            '72321' => 'auchan',
            '72324' => 'monoprix',
            '72325' => 'brico-depot',
            '72356' => 'hm',
            '72370' => 'decathlon',
            '72377' => 'bricomarche',
            '73518' => 'mr-bricolage',
            '73615' => 'Aldi',
            '80313' => 'carrefour',

        ];

        $companyDisplayPrefix = [
            '80314' => 'Action',
            '72305' => 'Lidl',
            '72314' => 'E. Leclerc',
            '72321' => 'Auchan',
            '72324' => 'Monoprix',
            '72325' => 'Brico Dépôt',
            '72356' => 'H&M',
            '72370' => 'Decathlon',
            '72377' => 'Bricomarché',
            '73518' => 'Mr. Bricolage',
            '73615' => 'aldi',
            '80313' => 'Carrefour',

        ];


        $week = "next";
        $week = $sTimes->getWeekNr($week);

        # get the two most recent folders on the FTP server
        $sFtp->connect($aFtpConfig);
        $allFolders = $sFtp->listFiles('.', '#\d{1,}#', FALSE);
        asort($allFolders);
        $folders[0] = array_pop($allFolders);
        $folders[1] = array_pop($allFolders);

        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $foldersToSearch = [];
        foreach ($folders as $singleFolder) {
            $this->_logger->info('parsing folder ' . $singleFolder);
            foreach ($sFtp->listFiles($singleFolder) as $companyFolder) {
                if (preg_match_all('#' . $companySearchPrefix[$companyId] . '.*(\d{2})-(\d{2})-(\d{2})#', $companyFolder, $matches)) {
                    if ($sTimes->isDateAhead($matches[1][0] . '.' . $matches[2][0] . '.20' . $matches[3][0]))
                        $foldersToSearch[] = $singleFolder . '/' . $companyFolder;
                }
            }
        }

        $brochureImages = [];
        foreach ($foldersToSearch as $singleFolder) {
            foreach ($sFtp->listFiles($singleFolder . '/medium', '#.jpg#') as $brochureImage) {
                $brochureImages[$singleFolder][] = $sFtp->downloadFtpToDir($singleFolder . '/medium/' . $brochureImage, $localPath);
            }
        }

        $sFtp->close();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        foreach ($brochureImages as $folderName => $brochureToCreate) {
            if (!preg_match_all('#.*(\d{2})-(\d{2})-(\d{2})#', $folderName, $matches)) {
                $this->_logger->err($companyId . ': unable to get end date.');
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle($companyDisplayPrefix[$companyId] . ': Offre hebdomadaire')
                ->setStart(date('d.m.Y'))
                ->setEnd($matches[1][0] . '.' . $matches[2][0] . '.20' . $matches[3][0])
                ->setVisibleStart($eBrochure->getStart())
                ->setVariety('leaflet')
                ->setUrl($sPdf->getPdfFromImageArray($brochureToCreate, $localPath))
                ->setBrochureNumber(md5($brochureToCreate[0]))
                ->setNational(1);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}
