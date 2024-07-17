<?php

/*
 * Brochure Crawler für Zookauf (ID: 29000)
 */

class Crawler_Company_Zookauf_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sArchive = new Marktjagd_Service_Input_Archive();

        $aArticles = array(
            '29000' => '#zookauf#i',
            '29088' => '#kiebitz#i',
            '72057' => '#heimtierpartner#i',
            '72064' => '#raiffeisen#i',
            '72065' => '#pet\s*power#i'
        );

        $cApiStores = $sApi->findStoresByCompany($companyId);

        $sFtp->connect('29000');
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        foreach ($sFtp->listFiles('.', '#\.zip$#') as $singleFile) {
            $localZipFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            $sArchive->unzip($localZipFile, $localPath);

        }
        foreach (scandir($localPath) as $singleLocalFile) {
            if (preg_match('#Datenfeed_([^\.]+?)\.xlsx?#', $singleLocalFile, $titleMatch)) {
                $localFeedFile[$titleMatch[1]] = $localPath . $singleLocalFile;
            }
        }

        foreach (scandir($localPath) as $singleLocalFile) {
            if (preg_match('#Teilnehmer#', $singleLocalFile)) {
                $aAssigmentData = $sExcel->readFile($localPath . '/' . $singleLocalFile, TRUE)->getElement(0)->getData();
                break;
            }
        }

        $aAssignment = array();
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($localFeedFile as $title => $path) {
            foreach ($aAssigmentData as $singleColumn) {

                if (!strlen($singleColumn['Datenfeed'])
                    || !preg_match($aArticles[$companyId], $singleColumn['Vertriebslinie'])) {
                    continue;
                }

                foreach ($cApiStores->getElements() as $eApiStore) {
                    if (!preg_match('#' . $singleColumn['PLZ'] . '#', $eApiStore->getZipcode())) {
                        continue;
                    }
                    $aAssignment[$title]['stores'][] = $eApiStore->getStoreNumber();
                }
            }

            $aFeedData = $sExcel->readFile($path, TRUE)->getElement(0)->getData();
            foreach ($aFeedData as $singleArticle) {
                if (!count($aAssignment[$title]['stores'])) {
                    continue;
                }
                $eArticle = new Marktjagd_Entity_Api_Article();
                foreach ($singleArticle as $key => $value) {
                    if (strlen($key)) {
                        $eArticle->{'set' . preg_replace('#\s*#', '', ucwords(preg_replace('#_#', ' ', $key)))}(preg_replace('#\n#', ' ', $value));
                    }
                }

                $eArticle->setStoreNumber(implode(',', $aAssignment[$title]['stores']))
                    ->setStart(date('d.m.Y', ($eArticle->getStart() - 25569) * 86400))
                    ->setEnd(date('d.m.Y', ($eArticle->getEnd() - 25569) * 86400))
                    ->setVisibleStart(date('d.m.Y', ($eArticle->getVisibleStart() - 25569) * 86400))
                    ->setVisibleEnd(date('d.m.Y', ($eArticle->getVisibleEnd() - 25569) * 86400));

                if (count($aAssignment[$title])) {
                    $cArticles->addElement($eArticle);
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);

    }
}
