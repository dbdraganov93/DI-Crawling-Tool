<?php
/**
 * Artikel Crawler für Street One (ID: 67898)
 */

class Crawler_Company_StreetOne_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $feedUrl = 'ftp://ftp-50246-153386966:55a940f8@ftp.semtrack.de/153386966.50246.csv';
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sFtp = new Marktjagd_Service_Transfer_Ftp();

        $ch = curl_init($feedUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($ch);
        curl_close($ch);

        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $localFile = $localPath . date('YmdHis') . '.csv';

        $fh = fopen($localFile, 'w+');
        fwrite($fh, $result);
        fclose($fh);

        $aData = $sExcel->readFile($localFile, TRUE, ';')->getElement(0)->getData();

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleData) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($singleData['offerID'])
                ->setTitle($singleData['name'])
                ->setPrice($singleData['prices'])
                ->setSuggestedRetailPrice($singleData['oldPrices'])
                ->setUrl($singleData['deepLink'])
                ->setImage($singleData['imageURL'])
                ->setText('Artikel NUR online erhältlich!<br/>' . $singleData['description'])
                ->setEan($singleData['EAN'])
                ->setColor($singleData['farbe'])
                ->setSize($singleData['groesse'])
                ->setNational(TRUE);

            $cArticles->addElement($eArticle);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);

        $fileName = $sCsv->generateCsvByCollection($cArticles);
        return $this->_response->generateResponseByFileName($fileName);
    }
}