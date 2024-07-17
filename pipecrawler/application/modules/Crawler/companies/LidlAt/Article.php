<?php

/**
 * Article Crawler für Lidl AT (ID: 73217)
 */
class Crawler_Company_LidlAt_Article extends Crawler_Generic_Company {
    private const MONTHS_MAP = [
        'Januar' => 1,
        'Jänner' => 1,
        'Februar' => 2,
        'März' => 3,
        'April' => 4,
        'Mai' => 5,
        'Juni' => 6,
        'Juli' => 7,
        'August' => 8,
        'September' => 9,
        'Oktober' => 10,
        'November' => 11,
        'Dezember' => 12
    ];

    /**
     * @throws Zend_Exception
     * @throws Exception
     */
    public function crawl($companyId) {
        $baseUrl        = 'https://www.lidl.at/';
        $searchNewUrl   = $baseUrl . 'sortimente/neu-bei-lidl-oesterreich';
        $searchDealsURL = $baseUrl . 'angebote';
        $sPage          = new Marktjagd_Service_Input_Page();
        $cArticles      = new Marktjagd_Collection_Api_Article();

        // initiate part 1 -> $searchNewUrl
        $sPage->open($searchNewUrl);
        $newsPage = $sPage->getPage()->getResponseBody();

        $doc = $this->createDomDocument($newsPage);
        $xpath = new DOMXPath($doc);
        // "data-list" is a unique attribute in each product node (at least so far)
        $productsRow = $xpath->query('//article[@data-list]');

        $newProductsArray = [];
        foreach ($productsRow as $rawProduct) {
            $this->createProductArray($newProductsArray, $rawProduct, $xpath, $baseUrl);
        }

        // Add new products
        foreach ($newProductsArray as $key => $newProduct) {

            if ($newProduct["productPrice"] == '') {
                $this->_logger->alert('The product ' . $newProduct["productText"] . ' does not have price');
                continue;
            }

            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($key)
                ->setTitle($newProduct["productText"])
                ->setText($newProduct["productText"])
                ->setPrice($newProduct["productPrice"])
                ->setImage($newProduct["imageUrl"])
                ->setUrl($newProduct["productUrl"])
                ->setStart(date("d.m.Y"))
                ->setEnd(date('d.m.Y', strtotime("+90 days"))) // longer validity as talked with Nicki
                ->setVisibleStart(date("d.m.Y"));

            $cArticles->addElement($eArticle);
        }

        // initiate part 2 -> $searchDealURL
        $sPage->open($searchDealsURL);
        $dealsPage = $sPage->getPage()->getResponseBody();

        $dealsDoc = $this->createDomDocument($dealsPage);
        $xpath = new DOMXPath($dealsDoc);

        // filter off advertisement (valid ones should have € symbol)
        $productsDealsRow = $xpath->query('//div[@data-currency="€"]');

        $dealsProductsArray = [];
        foreach ($productsDealsRow as $rawDealProduct) {
            $this->createProductArray($dealsProductsArray, $rawDealProduct, $xpath, $baseUrl);
        }

        // get deals Validity
        $xpathValidityQuery = '//h2[@class="sectionhead__title"]';
        $rawProductDealValidity = $xpath->query($xpathValidityQuery)->item(0)->textContent;
        if(!preg_match_all('#(?<monthDay>\d+)[.\s]+(?<monthName>\D+)$#', $rawProductDealValidity, $dealValidityArray)){
            $this->_logger->err(
                'Was not possible to find the validity for deals on ' . $xpathValidityQuery . PHP_EOL . $searchDealsURL
            );
        }

//        var_dump($dealValidityArray[0][0]);die();


        $rawProductDealValidityMonth = $xpath->query($xpathValidityQuery)->item(0)->textContent;
        if(!preg_match_all('#(?<monthDay>\d+)[.\s]+(?<monthName>\D+)$#', $rawProductDealValidityMonth, $dealValidityArrayMonth)){
            $this->_logger->err(
                'Was not possible to find the validity for deals on ' . $xpathValidityQuery . PHP_EOL . $searchDealsURL
            );

        }

        // get additional URLs deals from the main URL $searchDealsURL
        $additionalDealUrls = [];
        $additionalDealUrlsQuery = $xpath->query('//a[@class="theme__item "]');
        foreach ($additionalDealUrlsQuery as $additionalDealUrl) {
            if ($additionalDealUrl->getAttribute('href') == '') {
                $this->_logger->alert(
                    'Was not possible to find an "href" attribute in the <a> tag with content: ' .
                    $additionalDealUrl->textContent
                );
            }
            $additionalDealUrls[] = $baseUrl . $additionalDealUrl->getAttribute('href');
        }
        // Add additional products to $cStore -> enter in a loop with multiple URLs
        $this->getProductsAdditionalDealsUrls(
            $additionalDealUrls,
            $sPage,
            $baseUrl,
            $cArticles
        );

        $currentYear = date("Y");

        // add deals products
        foreach ($dealsProductsArray as $key => $dealProduct) {
            if ($dealProduct["productPrice"] == '') {
                $this->_logger->alert('The product ' . $dealProduct["productText"] . ' does not have price');
                continue;
            }


            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($key)
                ->setTitle($dealProduct["productText"])
                ->setText($dealProduct["productText"])
                ->setPrice($dealProduct["productPrice"])
                ->setImage($dealProduct["imageUrl"])
                ->setUrl($dealProduct["productUrl"])
                ->setStart($dealValidityArray[0] . $dealValidityArrayMonth[0]. $currentYear)
                ->setEnd($dealValidityArrayMonth[0]. strtotime("+4 days") . $currentYear)
                ->setVisibleStart($dealValidityArray[0] . $currentYear);

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }

    private function getProductsAdditionalDealsUrls(
        array $additionalDealUrls,
        Marktjagd_Service_Input_Page $sPage,
        string $baseUrl,
        Marktjagd_Collection_Api_Article $cArticles
    ) {
        if (empty($additionalDealUrls)) {
            throw new Exception('Was not possible to find any additional Deals in the $additionalDealUrls array');
        }

        $currentYear = date("Y");

        foreach ($additionalDealUrls as $key => $additionalDealUrl) {
            // Max pages! get only the first 10 deals Urls (can be up to 27 that are many days in the future)
            if ($key > 10){
                continue;
            }

            $this->_logger->info('Opening URL: ' . $additionalDealUrl);

            $sPage->open($additionalDealUrl);
            $dealsPage = $sPage->getPage()->getResponseBody();

            $dealsDoc = $this->createDomDocument($dealsPage);
            $xpath = new DOMXPath($dealsDoc);

            // filter off advertisement (valid ones should have € symbol)
            $productsDealsRow = $xpath->query('//div[@data-currency="€"]');

            $dealsProductsArray = [];
            foreach ($productsDealsRow as $rawDealProduct) {
                $this->createProductArray($dealsProductsArray, $rawDealProduct, $xpath, $baseUrl);
            }

            // get deals Validity first from @class="sectionhead__title"
            $xpathValidityQuery = '//header[@class="sectionhead"]';
            $rawProductDealValidity = $xpath->query($xpathValidityQuery)->item(0)->textContent;
            if(!preg_match_all('#(?<monthDay>\d+)[.\s]+(?<monthName>\D+)$#', $rawProductDealValidity, $dealValidityMatch)){
                $this->_logger->warn(
                    'Was not possible to find the validity CHECK: ' .
                    $xpathValidityQuery
                );
            }

//            var_dump($dealValidityMatch[0]);die();


            // try to get Validity from @class="sectionhead__title", month can be 'Juni' or '6'
            $xpathValidityQuery = '//header[@class="sectionhead"]';
            $rawProductDealValidityHeader = $xpath->query($xpathValidityQuery)->item(0)->textContent;
            if(!preg_match_all('#(?<monthDay>\d+)[.\s]+(?<monthName>\D+)$#', $rawProductDealValidityHeader, $dealValidityMatch)){
                $this->_logger->warn(
                    'Was not possible to find the validity for deals on for additional Deals in: ' . $xpathValidityQuery
                );
            }

//                        var_dump($dealValidityMatch[0]);die();

            if(preg_match_all('#(?<monthDay>\d+)[.\s]+(?<monthName>\D+)$#', $rawProductDealValidity, $dealValidityMatch)
            ){

//                var_dump($dealValidityMatch[0][0]);die();
                $validityWithNoEnd = false;
                if(array_key_exists($dealValidityMatch[0]['monthName'][0], self::MONTHS_MAP)){
                    // in case validity uses month names 'Juni'
                    $this->_logger->info('Validity without end found -> ' . $rawProductDealValidity[0]);
                    $monthNumber = self::MONTHS_MAP[$dealValidityMatch['monthName'][0]];

                    $validityWithNoEnd = $dealValidityMatch['monthDay'][0] . '.' . $monthNumber . '.' . $currentYear;

                    $endDate = DateTime::createFromFormat('d.m.Y', $validityWithNoEnd);



                    $endDate->modify('+3 day');
                    $endDate = $endDate->format('d.m.Y');

                } elseif (!$dealValidityMatch[0][0]) {
                    // in case validity has no end at all
                    $validityWithNoEnd = $dealValidityMatch[0][0] . $currentYear;
                    $endDate = DateTime::createFromFormat('d.m.Y', $dealValidityMatch[0][0] . $currentYear);

                    $endDate->modify('+3 day');
                    $endDate = $endDate->format('d.m.Y');
                } elseif ($dealValidityMatch[0][1]) {
                    // in case validity has end date but not complete
                    $validityWithNoEnd = $dealValidityMatch[0][0] . $currentYear;
                    $endDate = $dealValidityMatch[0][1] . $currentYear;
                }

            }

            // add additional deals products
            foreach ($dealsProductsArray as $key => $dealProduct) {

                if ($dealProduct["productPrice"] == '') {
                    $this->_logger->alert('The product ' . $dealProduct["productText"] . ' does not have price');
                    continue;
                }

                $eArticle = new Marktjagd_Entity_Api_Article();

                $eArticle->setArticleNumber($key)
                    ->setTitle($dealProduct["productText"])
                    ->setText($dealProduct["productText"])
                    ->setPrice($dealProduct["productPrice"])
                    ->setImage($dealProduct["imageUrl"])
                    ->setUrl($dealProduct["productUrl"])
                    ->setStart($validityWithNoEnd ?: $dealValidityMatch[0][0])
                    ->setEnd($validityWithNoEnd ? $endDate : $dealValidityMatch[0][1])
                    ->setVisibleStart($validityWithNoEnd ?: $dealValidityMatch[0][0]);

                $cArticles->addElement($eArticle);
            }

        }

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

    private function createProductArray(array &$productsArray, DOMElement $rawProduct, DOMXPath $xpath, string $baseUrl): void
    {
        // add productId
        $productId = $rawProduct->getAttribute('data-id');
        if ($productId == '') {
            throw new Exception('No product ID found, instead was found: ' .  $rawProduct->getAttribute('data-id'));
        }

        // gets 2 urls with different sizes. $explodedImgUrls[0] = large image
        $rawImgUrl = $xpath->query(
            $rawProduct->getNodePath() . '//a/picture/source[1]')
            ->item(0)
            ->getAttribute('data-srcset')
        ;
        $explodedImgUrls = $this->cleanUrls(explode(',', $rawImgUrl));

        // add imageUrl
        $productsArray[$productId]['imageUrl'] = $explodedImgUrls[0];
        if (!preg_match('#.jpg$|.png$#', $productsArray[$productId]['imageUrl'])) {
            $this->_logger->alert('Alert! -> No valid image found on ' .  $productsArray[$productId]['imageUrl']);
        }

        // add productUrl
        $productsArray[$productId]['productUrl'] = $baseUrl . $xpath->query($rawProduct->getNodePath() . '//a')
                ->item(0)->getAttribute('href');

        // add productPrice
        $productsArray[$productId]['productPrice'] = trim(
            $xpath->query($rawProduct->getNodePath() . '//span[@class="nuc-m-pricebox__price"]')
                ->item(0)->textContent
        );


        //TODO continue from here

        // add productText
        $productsArray[$productId]['productText'] = trim(
            $xpath->query($rawProduct->getNodePath() . '//h3[position() = 1]')
                ->item(0)->textContent
        );

//        var_dump(  $productsArray[$productId]['productText']);die();
        if ($productsArray[$productId]['productText'] == '') {
            throw new Exception('No product Title found found! ' .  $baseUrl);
        }
    }

    private function cleanUrls(array $rawUrls): array
    {
        $cleanUrls = [];

        foreach ($rawUrls as $rawUrl) {
            // cleans example ' https://at.cat-ret.assets.lidl/catalog5media/at/article/267333/third/lg/267333_01.jpg 1x'
            $cleanUrls[] = trim(preg_replace('#\s\dx#', '', $rawUrl));
        }

        return $cleanUrls;
    }
}
