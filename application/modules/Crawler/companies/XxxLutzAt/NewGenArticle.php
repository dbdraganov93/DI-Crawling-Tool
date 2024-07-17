<?php

/**
 * Crawler für New Gen Article für XXXLutz AT (ID: 73436 | Stage: 77066)
 */

class Crawler_Company_XxxLutzAt_NewGenArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {


        $bearerToken = 'Authorization=' . $this->getBearerToken();
        $baseUrl = 'https://digitalesflugblatt.premedia.at/';
        $brochureUrl = $baseUrl . 'api/Publications?' . $bearerToken;
        $sPage = new Marktjagd_Service_Input_Page();
        $sArticleCrawler = new Crawler_Company_XxxLutzAt_Article();

        $localPath = APPLICATION_PATH . '/../public/files/tmp/';
        $fileName = $sArticleCrawler->crawl($companyId)->getFileName();

        $sS3File = new Marktjagd_Service_Output_S3File('mjcsv', $fileName);
        $file = $sS3File->getFileFromBucket($fileName, $localPath);

        $sMJCsv = new Marktjagd_Service_Input_MarktjagdCsv();

        $cArticles = $sMJCsv->convertToCollection($file, 'articles');

        $aArticles = [];
        foreach ($cArticles->getElements() as $eArticle) {
            $aArticles[$eArticle->getArticleNumber()] = $eArticle;
        }

        $sPage->open($brochureUrl);
        $jBrochureInfos = $sPage->getPage()->getResponseAsJson();

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($jBrochureInfos as $singleJBrochure) {
            if (strtotime('now') > strtotime($singleJBrochure->validTo) || !$singleJBrochure->pdfURI) {
                continue;
            }

            $sPage->open('https://digitalesflugblatt.premedia.at/api/Documents?publikationId=' . $singleJBrochure->id . '&' . $bearerToken);
            $jArticles = $sPage->getPage()->getResponseAsJson();
            foreach ($jArticles as $singleJPage) {
                foreach ($singleJPage->artikelOptionen as $singleJArticle) {
                    $eArticle = new Marktjagd_Entity_Api_Article();

                    $eArticle->setArticleNumber(trim($singleJArticle->artNr . $singleJArticle->ausfkz))
                        ->setTitle($singleJArticle->artBez)
                        ->setSuggestedRetailPrice(round($singleJArticle->stattpreis, 2))
                        ->setPrice(round($singleJArticle->werbepreis, 2))
                        ->setText($singleJArticle->werbetext);

                    if ($singleJArticle->mediaInformation->hybrisURL) {
                        $sPage->open($singleJArticle->mediaInformation->hybrisURL);
                        $jInfos = $sPage->getPage()->getResponseAsJson();

                        if ($jInfos->images) {
                            $eArticle->setImage($jInfos->images[0]->url);
                        }
                    } elseif ($singleJArticle->mediaInformation->hauptbildURI) {
                        $eArticle->setImage($singleJArticle->mediaInformation->hauptbildURI);
                    }

                    if (!strlen($eArticle->getImage())) {
                        continue;
                    }

                    if (!array_key_exists($eArticle->getArticleNumber(), $aArticles)) {
                        $eArticle->setArticleNumber($eArticle->getArticleNumber() . '-NG');
                        $aArticles[$eArticle->getArticleNumber()] = $eArticle;
                        $this->_logger->info($companyId . ': new gen article added.');
                    } else {
                        $this->_logger->info($companyId . ': new gen article edited.');
                        $aArticles[$eArticle->getArticleNumber()]->setArticleNumber($eArticle->getArticleNumber() . '-NG');
                    }

                }
            }
        }

        foreach ($aArticles as $eArticle) {
            $cArticles->addElement($eArticle, true, 'complex', false);
        }

        return $this->getResponse($cArticles, $companyId);
    }

    private function getBearerToken() : string {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://navis.premedia.at/SecurityTokenService/connect/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'grant_type=password&username=DigitalesFlugblattApiOfferista&password=ApQ5yRfqg5-%2BR%40HL&scope=assetModule&client_id=DigitalesFlugblattClient&client_secret=KC~8H-%5BMgTQ%40D)K&acr_values=tenant%3Aofferista',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response);

        return $response->access_token;

    }
}