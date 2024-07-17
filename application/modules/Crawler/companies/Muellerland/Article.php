<?php

/*
 * Artikel Crawler für Müllerland (ID: 71441)
 */

class Crawler_Company_Muellerland_Article extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $aPattern = array('#Ã€#', '#ÃŒ#', '#Ã#', '#Ã¶#', '#Ã#', '#Ã©#', '#Ãš#', '#Â®#', '#Â#', '#Â°\s+#');
        $aReplace = array('ä', 'ü', 'ß', 'ö', 'Ö', 'é', 'è', '®', '', '°C');

        $sFtp->connect($companyId);
        $localFolder = $sFtp->generateLocalDownloadFolder($companyId);
        foreach ($sFtp->listFiles('.', '#\.csv#') as $singleFile) {
            $localArticelFile = $sFtp->downloadFtpToDir($singleFile, $localFolder);
        }

        $articleData = $sExcel->readFile($localArticelFile, TRUE, '|')->getElement(0)->getData();

        $aArticleGroups = array();
        foreach ($articleData as $singleArticle) {
            $aArticleGroups[substr($singleArticle['Artikelnummer im Shop'], 0, 9)][] = $singleArticle['Artikelnummer im Shop'];
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($articleData as $singleArticle) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($singleArticle['Artikelnummer im Shop'])
                    ->setEan($singleArticle['EAN / GTIN / Barcodenummer'])
                    ->setManufacturer(preg_replace($aPattern, $aReplace, $singleArticle['Herstellername']))
                    ->setTitle(preg_replace($aPattern, $aReplace, $singleArticle['Produktname']))
                    ->setPrice($singleArticle['Preis (Brutto)'])
                    ->setText(preg_replace('#([a-zäöüß]|\d)([A-Z])#', '$1<br/>$2', preg_replace($aPattern, $aReplace, $singleArticle['Produktbeschreibung'])))
                    ->setUrl($singleArticle['ProduktURL'])
                    ->setImage($singleArticle['BildURL'])
                    ->setShipping($singleArticle['Versand_Vorkasse']);

            if (preg_match('#(\s*Farbe:\s*([^\n<]+?)(\n|<))#', $eArticle->getText(), $colorMatch)) {
                $eArticle->setColor($colorMatch[2])
                        ->setText(preg_replace('#' . $colorMatch[1] . '#', '', $eArticle->getText()));
            }

            if (preg_match('#(\s*Maße:\s*([^\n]+)\s*)#', $eArticle->getText(), $sizeMatch)) {
                $eArticle->setSize($sizeMatch[2])
                        ->setText(preg_replace('#' . $sizeMatch[1] . '#', '', $eArticle->getText()));
            }

            $cArticles->addElement($eArticle);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
