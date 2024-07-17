<?php

/**
 * Artikelcrawler für Kaiser's Tengelmann ID: 240
 */
class Crawler_Company_Kaisers_Article extends Crawler_Generic_Company {

    protected $_baseUrl = 'http://213.61.219.185';

    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();
        $sNumbers = new Marktjagd_Service_Text_Numbers();
        $cArticle = new Marktjagd_Collection_Api_Article();

        $page = $sPage->getPage();
        $page->setAlwaysHtmlDecode(false)
             ->setAlwaysConvertCharset(false)
             ->setAlwaysStripComments(false)
             ->setUseCookies(true);

        $sPage->setPage($page);

        $loginUrl = $this->_baseUrl
            . '/cgi-bin/WebObjects/AdSuiteRS.woa/1/ra/std/Login/login.xml?user=Kopie-Export_Web&pwd=y6MfyX';

        $vertriebsbereiche = array(
            '82' => 'Berlin/Umland',
            '80' => 'Nordrhein',
            '22' => 'München/Oberbayern'
        );

        $urlPrefix = $this->_baseUrl . '/cgi-bin/WebObjects/AdSuiteRS.woa/1/ra/std/Advertising/var/'
            . 'searchExportAdvertisingsWithProductItems.xml?firstSearchWord=KW';

        $currentKw = date('W', strtotime('this week'));
        $yearOfCurrentKw = date('o', strtotime('this week'));

        $nextKw = date('W', strtotime('next week'));
        $yearOfNextKw = date('o', strtotime('next week'));
        
        if (!$sPage->open($loginUrl)) {
            throw new Exception($companyId . ': unable to login via url: ' . $loginUrl);
        }

        $xmlLogin = simplexml_load_string($sPage->getPage()->getResponseBody());
        $wosId = (string) $xmlLogin->id;

        $articleUrls = array();
        $articleUrls[] =  $urlPrefix . $currentKw . '&secondSearchWord=' . $yearOfCurrentKw . '&wosid=';
        $articleUrls[] =  $urlPrefix . $nextKw . '&secondSearchWord=' . $yearOfNextKw . '&wosid=';

        foreach ($articleUrls as $articleUrl) {
            $logger->log('open ' . $articleUrl . $wosId, Zend_Log::INFO);
            if (!$sPage->open($articleUrl . $wosId)) {
                throw new Exception('Kaiser\'s Tengelmann (ID: 240) article crawler' . "\n"
                        . 'couldn\'t open article page ' . $articleUrl . $wosId);
            }

            $xmlArticles = simplexml_load_string($sPage->getPage()->getResponseBody());
            $productItems = $xmlArticles->Advertising->placedProductItems;
            if (!count($productItems)) {
                $logger->log(
                    'Kaiser\'s Tengelmann (ID: 240) article crawler' . "\n"
                    . 'no articles available on ' . $articleUrl . $wosId, Zend_Log::WARN);
                continue;
            }

            // Laufzeiten ermitteln
            $startTime = (string) $xmlArticles->Advertising->startPlaningDate;
            $endTime = (string) $xmlArticles->Advertising->endPlaningDate;
            $startTime = date('d.m.Y', strtotime($startTime));
            $endTime = date('d.m.Y', strtotime($endTime));

            foreach ($productItems->ProductItem as $productItem) {
                foreach ($productItem->toProductItemRegionSpecifics->ProductItemRegionSpecific as $regionInfos) {
                    $apiArticle = new Marktjagd_Entity_Api_Article();
                    $apiArticle->setArticleNumber(
                        trim((string) $regionInfos->toRegion->code) . '-'
                            . trim((string) $productItem->productCode))
                               ->setTitle(trim((string) $regionInfos->title))
                               ->setAmount(trim((string) $regionInfos->grammatur))                              
                               ->setText(trim((string) $regionInfos->subTitle) . ' ' . trim((string) $regionInfos->grammatur));

                    if (strlen((string) $regionInfos->crossedPrice)){
                        $apiArticle->setSuggestedRetailPrice($sNumbers->normalizePrice((string) $regionInfos->crossedPrice));
                    }
                    
                    // Wenn Basispreis gleich dem Werbepreise, dann diesen verwenden                                       
                    if ((string) $regionInfos->basePriceEqualAdvertisingPrice == 'true') {
                        if (strlen((string) $regionInfos->basisPriceTag)){
                            $apiArticle->setPrice($sNumbers->normalizePrice((string) $regionInfos->basisPriceTag));
                        }
                    } else {
                        if (strlen((string) $regionInfos->price)){
                            $apiArticle->setPrice($sNumbers->normalizePrice((string) $regionInfos->price));                                                        
                        }
                    }

                    if (strlen((string) $regionInfos->presentationName)) {
                        $apiArticle->setTitle((string) $regionInfos->presentationName);
                    }

                    if (strlen((string) $regionInfos->additionalText0) > 5) {
                        if (strlen($apiArticle->getText())) {
                            $apiArticle->setText($apiArticle->getText() . '<br>');
                        }
                        $apiArticle->setText($apiArticle->getText() . $regionInfos->additionalText0);
                    }

                    if (strlen((string) $regionInfos->additionalText1) > 5) {
                        if (strlen($apiArticle->getText())) {
                            $apiArticle->setText($apiArticle->getText() . '<br>');
                        }
                        $apiArticle->setText($apiArticle->getText() . $regionInfos->additionalText1);
                    }

                    if (strlen((string) $regionInfos->additionalText2) > 5) {
                        if (strlen($apiArticle->getText())) {
                            $apiArticle->setText($apiArticle->getText() . '<br>');
                        }
                        $apiArticle->setText($apiArticle->getText() . $regionInfos->additionalText2);
                    }

                    // Kleingedruckte
                    if (strlen((string) $regionInfos->smallPrintText) > 3) {
                        if (strlen($apiArticle->getText())) {
                            $apiArticle->setText($apiArticle->getText() . '<br>');
                        }
                        $apiArticle->setText($apiArticle->getText() . $regionInfos->smallPrintText);
                    }

                    // Info bzgl. Basis-Preis (wenn vorhanden)
                    if (strlen((string) $regionInfos->basisPriceTag)) {                        
                        $apiArticle->setText($apiArticle->getText() . '<br><br>');                        
                        $apiArticle->setText($apiArticle->getText() . (string) $regionInfos->basisPriceTag);
                    }

                    // Preishinweise (1kg, Quadratmeter, Tür ab, laufende Meter,... )
                    if (strlen((string) $regionInfos->prePriceText)) {
                        if (strlen($apiArticle->getText())) {
                            $apiArticle->setText($apiArticle->getText() . '<br><br>');
                        }
                        $apiArticle->setText($apiArticle->getText() . $regionInfos->prePriceText);
                    }

                    $apiArticle->setText(preg_replace('#\##', ' ', $apiArticle->getText()));

                    // Bild(er)
                    $images = array();
                    
                    // wenn echtes Media Object, dann das zusätzlich nehmen
                    if (is_object($regionInfos->toImages->ProductItemRegionSpecificProductImage->toProductImage)
                        && strlen((string) $regionInfos->toImages->ProductItemRegionSpecificProductImage->toProductImage->toMediaObject->downloadPreviewFile)) {
                        $images[] = $this->_baseUrl . (string) $regionInfos->toImages->ProductItemRegionSpecificProductImage->toProductImage->toMediaObject->downloadPreviewFile;
                    }

                    // Bild aus Snippet holen (Fallback)
                    if (strlen((string) $regionInfos->toSnippet->downloadPreviewFile)) {
                        $images[] = $this->_baseUrl . (string) $regionInfos->toSnippet->downloadPreviewFile;
                    }
                    
                    $apiArticle->setImage(implode(',', $images));

                    // Gültigkeit der Artikel (für alle Artikel des Advertising gleich)
                    $apiArticle->setEnd($endTime);
                    $apiArticle->setStart($startTime);
                    $apiArticle->setVisibleStart($startTime);
                    
                    
                    if (strlen((string) $regionInfos->additionalText3) > 5) {
                        if (preg_match('#freitag\s*und\s*samstag#i', $regionInfos->additionalText3)) {
                            $sTimes = new Marktjagd_Service_Text_Times();
                            $week = date('W', strtotime($startTime . ' + 1 week'));
                            $year= date('Y', strtotime($startTime . ' + 1 week'));
                            $apiArticle->setStart($sTimes->findDateForWeekday($year, $week, 'Fr'))
                                ->setEnd($sTimes->findDateForWeekday($year, $week, 'Sa'));
                        }
                    }
                    
                    // Distribution
                    if ((string) $regionInfos->toRegion->code != '00') {
                        $apiArticle->setDistribution($vertriebsbereiche[(string) $regionInfos->toRegion->code]);
                    }

                    $cArticle->addElement($apiArticle);
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticle);
        return $this->_response->generateResponseByFileName($fileName);
    }
}