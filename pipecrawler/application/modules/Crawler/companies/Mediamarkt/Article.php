<?php

/**
 * Artikel Crawler für Media Markt (ID: 14)
 */
class Crawler_Company_Mediamarkt_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        ini_set('memory_limit', '4G');
        $sourceUrl = 'https://transport.productsup.io/87589f13e84a74814706/channel/211358/marktjagd.csv';
        $sDownload = new Marktjagd_Service_Transfer_Download();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();


        $destinationFile = $sDownload->generateLocalDownloadFolder($companyId);
        $localFile = $sDownload->downloadByUrl($sourceUrl, $destinationFile);
        if (!$localFile) {
            throw new Exception($companyId . ': cannot download article file');
        }

        $fh = fopen($localFile, 'r');
        $aHeader = [];
        $aData = [];
        while (($row = fgetcsv($fh, 0, ';')) !== FALSE) {
            if (!count($aHeader)) {
                $aHeader = $row;
                continue;
            }
            $aData[] = array_combine($aHeader, $row);
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleRow) {
            if (!strlen($singleRow['oldPrices']) || preg_match('#Film\s*\&\s*Musik#i', $singleRow['Produktgruppe-im-Shop'])
                || preg_match('#\[DVD\]#i', $singleRow['Produktname'])) {
                continue;
            }

            $eArticle = new Marktjagd_Entity_Api_Article();

            // Pornografische Inhalte rausfiltern
            if (preg_match('#eroti#is', $singleRow['Produktbeschreibung']) || preg_match('#Ab\s*18\s*#is', $singleRow['Produktbeschreibung'])
                || preg_match('#(eroti|love\s*toy|vibrator|analplug)#is', $singleRow['Produktname'])) {
                continue;
            }

            $eArticle->setArticleNumber($singleRow['Artikelnummer-im-Shop'])
                ->setTitle($singleRow['Produktname'])
                ->setPrice(preg_replace('#\s*EUR#', '', $singleRow['Preis']))
                ->setUrl($singleRow['ProduktURL'])
                ->setImage($singleRow['BildURL'])
                ->setText($singleRow['Produktbeschreibung'])
                ->setEan($singleRow['EAN-Barcodenummer'])
                ->setManufacturer($singleRow['Herstellername'])
                ->setSuggestedRetailPrice(preg_replace('#\s*EUR#', '', $singleRow['oldPrices']));

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }
}
