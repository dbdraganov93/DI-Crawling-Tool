<?php

/**
 * Discover Artikel-Crawler für Hofmeister (ID: 69717)
 */

class Crawler_Company_Hofmeister_DiscoverArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # adjust the campaign array when a new campaign starts                  #
        # update the articles daily                                             #
        #                                                                       #
        #########################################################################

        $campaigns = [
            1 => [
                'article_url' => 'https://hofmeister.de/backend/export/index/offerista-hofmeister.csv?feedID=66&hash=fe248f9a0c9ce24d602286351a3963a8',
                'campaign_title' => 'Auf den Sommer vorbereiten',
                'campaign_pdf' => 'Hofmeister_KW25.pdf',
                'campaign_categories' => '20210628_Discover-Data-Set_Hofmeister.xlsx',
                'brochure_number_prefix' => 'KW33',
                'start_date' => '22.06.2021',
                'end_date' => '30.09.2021',
            ]
        ];


        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $cArticles = new Marktjagd_Collection_Api_Article();

          $sFtp->connect($companyId. '/KW01');

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);



        foreach ($campaigns as $campaign) {

            #get the campaign's article file
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $campaign['article_url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Basic ZmVlZDplaWNob3Uxb2htNEk=',
                    'Cookie: SHOPWAREBACKEND=13bbe67e2635a7a0e75e205fb013f142a908be5332bb2866d191e3c2f2cf8b39'
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            $localArticleFile = $localPath .$campaign['brochure_number_prefix'] .'article.csv';
            $fh = fopen($localArticleFile, 'w+');
            fwrite($fh, $response);
            fclose($fh);

            $discoverArticles = [];
            $localCategoryFile = $sFtp->downloadFtpToDir($campaign['campaign_categories'] , $localPath);

            $aData = $sPss->readFile($localCategoryFile, TRUE)->getElement(0)->getData();


            foreach ($aData as $singleRow) {
                $discoverArticles[$singleRow['article_number']] = 1;
            }

            $aData = $sPss->readFile($localArticleFile, TRUE, ';')->getElement(0)->getData();

            foreach ($aData as $singleRow) {

                if(!isset($discoverArticles[$singleRow['Artikelnummer im Shop ']])) {
                    continue;
                }
               unset($discoverArticles[$singleRow['Artikelnummer im Shop ']]);

                if($singleRow['Streichpreis'] = '0,00') {
                    $singleRow['Streichpreis'] = null;
                }


                $eArticle = new Marktjagd_Entity_Api_Article();
                $eArticle->setArticleNumber($singleRow['Artikelnummer im Shop '])
                    ->setTitle($singleRow['Produktname'])
                    ->setText($singleRow['Produkt Beschreibung'])
                    ->setPrice($singleRow['Streichpreis']?? $singleRow['Preis'])
                    ->setSuggestedRetailPrice($singleRow['Streichpreis'] ? $singleRow['Preis'] : null)
                    ->setShipping($singleRow['Versandkosten'])
                    ->setArticleNumberManufacturer($singleRow['Original Herstellerartikelnummern'])
                    ->setImage($singleRow['Bild URL'])
                    ->setUrl($singleRow['Produkt URL'])
                    ->setTrademark($singleRow['Hersteller'])
                    ->setColor($singleRow['Farbe'])
                    ->setEan($singleRow['EAN'])
                    ->setStart($campaign['start_date'])
                    ->setEnd($campaign['end_date'])
                    ->setVisibleStart($campaign['start_date']);

                $cArticles->addElement($eArticle, TRUE, 'complex', FALSE);
            }

        }
        # print the missing articles
#        Zend_Debug::dump($discoverArticles);

        return $this->getResponse($cArticles, $companyId);
    }
}
