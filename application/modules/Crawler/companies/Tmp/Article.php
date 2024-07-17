<?php
/**
 * Artikelcrawler für toom Baumarkt (ID: 123)
 */
class Crawler_Company_Toom_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();

        $cArticle = new Marktjagd_Collection_Api_Article();

        $page = $sPage->getPage();
        $page->setAlwaysHtmlDecode(false)
             ->setAlwaysConvertCharset(false)
             ->setAlwaysStripComments(false)
             ->setUseCookies(true);

        $sPage->setPage($page);

        $nextKW = date('W', strtotime('next week'));
        $yearOfKW = date('o', strtotime('next week'));

        $domain         = 'http://213.61.219.41';
        $loginUrl       = $domain . '/cgi-bin/WebObjects/ToomAdSuite.woa/1/ra/std/Login/login.xml?user=marktjagd&pwd=mK3iHD';
        $articleUrl     = $domain . '/cgi-bin/WebObjects/ToomAdSuite.woa/1/ra/std/Advertising/var/'
                                    . 'searchExportAdvertisingsWithProductItems.xml?firstSearchWord=KW' . $nextKW . '&secondSearchWord=' . $yearOfKW . '&wosid=';

        // wosid / session id ermitteln
        if (!$sPage->open($loginUrl)) {
            throw new Exception('toom (ID: 123) article crawler' . "\n"
                    . 'couldn\'t open login page');
        }

        $xmlLogin = simplexml_load_string($sPage->getPage()->getResponseBody());
        $wosId = (string) $xmlLogin->id;

        // Advertising suchen
        // http://213.61.219.41/cgi-bin/WebObjects/ToomAdSuite.woa/1/ra/std/TimeLimitedCampaign/var/searchAdvertising.xml?wosid=xCS9Si9Pt7mnmu2D1DYrJM&firstSearchWord=KW50&secondSearchWord=2013

        // Produkte aus Advertisung finden
        // http://213.61.219.41/cgi-bin/WebObjects/ToomAdSuite.woa/1/ra/std/Advertising/var/productItems.xml?withProductInfos=true&wosid=xCS9Si9Pt7mnmu2D1DYrJM&advertisingId=1000583

        // Artikelliste für die nächste KW abrufen
        $logger->log('open ' . $articleUrl . $wosId, Zend_Log::INFO);
        if (!$sPage->open($articleUrl . $wosId)) {
            throw new Exception('toom (ID: 123) article crawler' . "\n"
                    . 'couldn\'t open article page ' . $articleUrl . $wosId);
        }

        $xmlArticles = simplexml_load_string($sPage->getPage()->getResponseBody());
        $productItems = $xmlArticles->Advertising->placedProductItems;

        // Laufzeiten ermitteln
        $startTime = (string) $xmlArticles->Advertising->startPlaningDate;
        $endTime = (string) $xmlArticles->Advertising->endPlaningDate;

        $startTime = date('d.m.Y', strtotime($startTime));
        $endTime = date('d.m.Y', strtotime($endTime));

        // erster Lauf - alle Unterprodukte/Varianten finden und in array speichern
        // es werden hier keine Varianten aufgenommen
        $subProductCodes = array();
        foreach ($productItems->ProductItem as $productItem) {
            foreach ($productItem->toProductItemRegionSpecifics->ProductItemRegionSpecific as $regionInfos) {
                if (strlen((string) $regionInfos->tlcSubProducts)){
                    $subProductCodes = array_merge($subProductCodes, explode(',', (string) $regionInfos->tlcSubProducts));
                }
            }
        }

        foreach ($productItems->ProductItem as $productItem) {
            // keine Varianten neu aufnehmen
            if (in_array($productItem->productCode, $subProductCodes)){
                continue;
            }
            foreach ($productItem->toProductItemRegionSpecifics->ProductItemRegionSpecific as $regionInfos) {
                // nur nationale Produkte aufnehmen
                if ((string) $regionInfos->toRegion->code != '00'){
                    continue;
                }

                $apiArticle = new Marktjagd_Entity_Api_Article();

                $apiArticle->setArticleNumber(trim((string) $productItem->productCode))
                           ->setTitle(trim((string) $regionInfos->title))
                           ->setAmount(trim((string) $regionInfos->grammatur))
                           ->setSuggestedRetailPrice(trim((string) $regionInfos->crossedPrice))
                           ->setText(trim((string) $regionInfos->productText));

                // Wenn Basispreis gleich dem Werbepreise, dann diesen verwenden
                if ((string) $regionInfos->basePriceEqualAdvertisingPrice == 'true'){
                    $apiArticle->setPrice(trim((string) $regionInfos->basisPriceTag));
                } else {
                    $apiArticle->setPrice(trim((string) $regionInfos->price));
                }

                if (strlen((string) $regionInfos->presentationName)){
                    $apiArticle->setTitle((string) $regionInfos->presentationName);
                }

                if (strlen((string) $regionInfos->additionalText0)){
                    if (strlen($apiArticle->getText())){
                        $apiArticle->setText($apiArticle->getText() . '<br>');
                    }
                    $apiArticle->setText($apiArticle->getText() . $regionInfos->additionalText0);
                }

                if (strlen((string) $regionInfos->additionalText1)){
                    if (strlen($apiArticle->getText())){
                        $apiArticle->setText($apiArticle->getText() . '<br>');
                    }
                    $apiArticle->setText($apiArticle->getText() . $regionInfos->additionalText1);
                }

                if (strlen((string) $regionInfos->additionalText2)){
                    if (strlen($apiArticle->getText())){
                        $apiArticle->setText($apiArticle->getText() . '<br>');
                    }
                    $apiArticle->setText($apiArticle->getText() . $regionInfos->additionalText2);
                }

                if (strlen((string) $regionInfos->additionalText3)){
                    if (strlen($apiArticle->getText())){
                        $apiArticle->setText($apiArticle->getText() . '<br>');
                    }
                    $apiArticle->setText($apiArticle->getText() . $regionInfos->additionalText3);
                }

                // Kleingedruckte
                if (strlen((string) $regionInfos->smallPrintText)){
                    if (strlen($apiArticle->getText())){
                        $apiArticle->setText($apiArticle->getText() . '<br>');
                    }
                    $apiArticle->setText($apiArticle->getText() . $regionInfos->smallPrintText);
                }

                // Info bzgl. Basis-Preis (wenn vorhanden)
                if (strlen((string) $regionInfos->basisPricePrefix) && strlen((string) $regionInfos->basisPriceTag)){
                    if (strlen($apiArticle->getText())){
                        $apiArticle->setText($apiArticle->getText() . '<br><br>');
                    }
                    $apiArticle->setText($apiArticle->getText() . '(' . (string) $regionInfos->basisPricePrefix .' = ' . (string) $regionInfos->basisPriceTag . ')');
                }

                // Preishinweise (1kg, Quadratmeter, Tür ab, laufende Meter,...)
                if (strlen((string) $regionInfos->prePriceText)){
                    if (strlen($apiArticle->getText())){
                        $apiArticle->setText($apiArticle->getText() . '<br><br>');
                    }
                    $apiArticle->setText($apiArticle->getText() . $regionInfos->prePriceText);
                }

                $apiArticle->setText(trim($apiArticle->getText()));

                // Bild
                $apiArticle->setImage($domain . (string) $regionInfos->toImages->ProductItemRegionSpecificProductImage->toProductImage->toMediaObject->downloadOriginalFile);

                // Gültigkeit der Artikel (für alle Artikel des Advertising gleich)
                $apiArticle->setEnd($endTime);
                $apiArticle->setStart($startTime);
                $apiArticle->setVisibleStart($startTime);

                $cArticle->addElement($apiArticle);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticle);

        return $this->_response->generateResponseByFileName($fileName);
    }
}