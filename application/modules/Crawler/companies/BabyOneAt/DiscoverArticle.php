<?php

/**
 * Article crawler for BabyOne (ID: 73170)
 */

class Crawler_Company_BabyOneAt_DiscoverArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sPage = new Marktjagd_Service_Input_Page();

        $gSheetId = '1gtNocU-e2-i1uBNu0CMuyTnbXbyydMoqLPszFNfc_R0';

        # look for an articles file on the FTP server
        $localPath = $sFtp->connect('28698', TRUE);
        $sFtp->changedir('dynamic_flyer_and_discover');

        foreach ($sFtp->listFiles() as $singleFtpFile) {
            if (preg_match('#Artikelliste#', $singleFtpFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleFtpFile, $localPath);
                $sFtp->close();
                break;
            }
        }
        # look for a dynamic brochure in the marketing plan (no dyn. brochure, nothing to do)
        $sGSheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $brochurePlan = $sGSheet->getFormattedInfos($gSheetId, 'A1', 'I', 'geplant');
        $today = time();

        foreach ($brochurePlan as $singleRow) {
            $datePattern = '#(\d){2}\.(\d){2}\.(\d){4}#';
            if (!preg_match($datePattern, $singleRow['Startdatum'])) {
                $this->_logger->warn('Something is wrong with the Start Date Regex: ' . $singleRow['Startdatum']);
            }
            if (!preg_match($datePattern, $singleRow['Enddatum'])) {
                $this->_logger->warn('Something is wrong with the End Date Regex: ' . $singleRow['Enddatum']);
            }

            if (!empty($singleRow['PDF Datei']) && $singleRow['AT'] == 'ja' && preg_match('#discover#i', $singleRow['Kampagne']) && strtotime($singleRow['Enddatum']) >= $today) {
                $startDate = $singleRow['Startdatum'];
                $endDate = $singleRow['Enddatum'];
                $utmParameter = preg_replace('#^[^\?]+#', '', $singleRow['Link AT']);
            }
        }

//        if(!$localArticleFile || !$endDate) {
//            $this->_logger->info('no articles need to be imported');
//            $this->_response->setIsImport(false);
//            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
//            return $this->_response;
//        }

        $aData = $sPss->readFile($localArticleFile, FALSE)->getElement(0)->getData();

        # build the URL for each article
        $cArticles = new Marktjagd_Collection_Api_Article();
        $i = 0;
        $failedArticles = array();
        $offlineArticles = array();
        foreach ($aData as $singleArticle) {

            if (empty($singleArticle[1]))
                continue;

            $i += 1;
            if ($i % 100 == 0) {
                $this->_logger->info($i);
            }

            [$singleArticle['Kategorie'], , , $singleArticle['Artikelnummer']] = explode(';', $singleArticle[1]);
            $singleArticle['URL'] = 'https://www.babyone.at/' . $singleArticle['Artikelnummer'] . '.html';

            $this->_logger->info($i);
            if (!preg_match('#^http#', $singleArticle['URL'])) {
                continue;
            }

            # crawl the article data
            $urlWithoutTracking = preg_replace('#([^\?]+?)\?.+#', '$1', $singleArticle['URL']);
            $sPage->open($urlWithoutTracking);
            $page = $sPage->getPage()->getResponseBody();
            $xPath = new DOMXPath($this->createDomDocument($page));

            // title
            $title = trim($xPath->query('//h1[@class="product-detail-name"]')->item(0)->textContent);
            if (empty($title)) {
                $productsSkipped[] = $singleArticle['Artikelnummer'];
                $this->_logger->warn('This URL was skipped, no title found: ' . $singleArticle['URL']);
                continue;
            }

            // description and manufacturer
            $description = trim($xPath->query('//div[@class="product-detail-description-text"]')->item(0)->textContent);
            $manufacturer = trim($xPath->query('//span[@class="manufacturer-name"]')->item(0)->textContent);

            // image
            $imageNode = $xPath->query('//div[@class="gallery-slider-item is-contain js-magnifier-container"]/img')->item(0); //srcset
            $imageUrl = $imageNode->getAttribute('data-full-image');
            if (empty($imageUrl)) {
                $productsSkipped[] = $singleArticle['Artikelnummer'];
                $this->_logger->warn('This URL was skipped, no image found: ' . $singleArticle['URL']);
                continue;
            }

            $price = preg_replace('#\s*\€.*#', '', trim(
                $xPath->query('//p[@class="product-detail-price with-list-price"]')->item(0)->textContent
            ));

            if (empty($price)) {
                $price = preg_replace('#\s*€.*#', '', trim(
                    $xPath->query('//p[@class="product-detail-price"]')->item(0)->textContent
                ));
            }
            if (empty($price)) {
                $productsSkipped[] = $singleArticle['Artikelnummer'];
                $this->_logger->warn('This URL was skipped, no price found: ' . $singleArticle['URL']);
                continue;
            }

            $originalPrice = preg_replace('#\s*\€.*#', '', str_replace('UVP ', '', trim(
                $xPath->query('//span[@class="list-price-price"]')->item(0)->textContent
            )));

            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setTitle($title)
                ->setUrl($singleArticle['URL'] . $utmParameter)
                ->setStart($startDate)
                ->setEnd($endDate)
                ->setVisibleStart($eArticle->getStart())
                ->setArticleNumber($singleArticle['Artikelnummer'])
                ->setText($description)
                ->setPrice($price)
                ->setSuggestedRetailPrice($originalPrice)
                ->setManufacturer($manufacturer)
                ->setImage($imageUrl);

            $cArticles->addElement($eArticle, true, 'complex', false);
        }
        if (count($failedArticles) < 0) {
            $this->_logger->info($companyId . ': failed articles ' . count($failedArticles));
            foreach ($failedArticles as $failedArticle) {
                $this->_logger->info($companyId . ': failed article: ' . $failedArticle);
            }
        }

        if (count($offlineArticles) < 0) {
            $this->_logger->info($companyId . ': failed articles ' . count($offlineArticles));
            foreach ($offlineArticles as $offlineArticle) {
                $this->_logger->info($companyId . ': offline article: ' . $offlineArticle);
            }
        }

        return $this->getResponse($cArticles, $companyId);
    }

    private function createDomDocument(string $url): DOMDocument
    {
        // ignore warnings
        $old_libxml_error = libxml_use_internal_errors(true);
        $domDoc = new DOMDocument();
        $domDoc->loadHTML($url);
        libxml_use_internal_errors($old_libxml_error);

        return $domDoc;
    }
}
