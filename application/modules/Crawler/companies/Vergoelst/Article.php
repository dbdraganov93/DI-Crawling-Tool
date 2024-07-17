<?php

class Crawler_Company_Vergoelst_Article extends Crawler_Generic_Company {

    public function crawl($companyId) {

        function sortByImage($aElementOne, $aElementTwo) {
            if (strlen($aElementOne['bild url']) == strlen($aElementTwo['bild url'])) {
                return 0;
            }

            return (strlen($aElementOne['bild url']) > strlen($aElementTwo['bild url'] ? -1 : 1));
        }

        $logger = Zend_Registry::get('logger');
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPhpExcel = new Marktjagd_Service_Input_PhpExcel();

        $sFtp->connect($companyId);
        $localDirectory = $sFtp->generateLocalDownloadFolder($companyId);
        $localFileName = $sFtp->downloadFtpToDir('articles_archive.csv', $localDirectory);
        file_put_contents($localFileName, Marktjagd_Service_Text_Encoding::toUTF8(file_get_contents($localFileName)));

        $aTires = $sPhpExcel->readFile($localFileName, true, "\t");
        $aTires = $aTires->getElements();
        $aTires = $aTires[0]->getData();

        usort($aTires, 'sortByImage');

        $cArticles = new Marktjagd_Collection_Api_Article();

        foreach ($aTires as $aSingleTire) {
            if (!strlen($aSingleTire['bild url']) || count($cArticles) == 8000) {
                continue;
            }
            $eArticle = new Marktjagd_Entity_Api_Article();
            $sDescription = $aSingleTire['beschreibung'];
            if (strlen($aSingleTire['kraftstoffeffizienzklasse'])) {
                $sDescription .= '<br>Kraftstoffeffizienzklasse ' . $aSingleTire['kraftstoffeffizienzklasse'];
            }
            if (strlen($aSingleTire['nasshaftungsklasse'])) {
                $sDescription .= '<br>Nasshaftungsklasse ' . $aSingleTire['nasshaftungsklasse'];
            }
            if (strlen($aSingleTire['rollgeraeusch'])) {
                $sDescription .= '<br>Rollgeräusch ' . $aSingleTire['rollgeraeusch'];
            }

            if (!preg_match('#http[s]{0,1}:\/\/#', $aSingleTire['bild url'])) {
                $aSingleTire['bild url'] = preg_replace('#\/\/#', 'http://', $aSingleTire['bild url']);
            }

            if (!preg_match('#http[s]{0,1}:\/\/\/\/#', $aSingleTire['bild url'])) {
            	$aSingleTire['bild url'] = str_replace(':////', '://', $aSingleTire['bild url']);
            }            
            
            $eArticle->setTitle($aSingleTire['titel'])
                    ->setArticleNumber($aSingleTire['id'])
                    ->setTags($aSingleTire['produktart'])
                    ->setText($sDescription)
                    ->setPrice($aSingleTire['preis'])
                    ->setEan($aSingleTire['ean'])
                    ->setUrl($aSingleTire['link'])
                    ->setImage($aSingleTire['bild url'])
                    ->setManufacturer($aSingleTire['marke'])
                    ->setShipping(preg_replace('#\:#', '', $aSingleTire['versand']));
            $cArticles->addElement($eArticle);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
