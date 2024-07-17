<?php

/*
 * Article Crawler für Hofer AT (ID: 72982)
 */

class Crawler_Company_HoferAt_Article extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl        = 'https://www.hofer.at/';
        $searchUrl1     = $baseUrl . 'de/angebote/aktionen.html';
        $searchUrl2     = $baseUrl . 'de/angebote/hofer-preis-dauerhaft-guenstiger.html';
        $sPage          = new Marktjagd_Service_Input_Page();
        $cArticles      = new Marktjagd_Collection_Api_Article();

        // initiate part 1 -> $searchUrl1
        $sPage->open($searchUrl1);
        $page1 = $sPage->getPage()->getResponseBody();

        $doc = $this->createDomDocument($page1);
        $xpath = new DOMXPath($doc);
        $rawNodeHeaders = $xpath->query('//main');

        $headers1Array = [];
        $this->createValidityHeadersArray($rawNodeHeaders, $headers1Array, $xpath);

        $rawProductsRowNodes = $xpath->query('//div[@class="E12-grid-gallery"]');
        $products1Array = [];
        $this->createProductsArray($rawProductsRowNodes, $products1Array, $xpath);

        $this->addToArticleCollection($products1Array, $headers1Array, $cArticles);


        // initiate part 2 -> $searchUrl2
        $sPage->open($searchUrl2);
        $page2 = $sPage->getPage()->getResponseBody();

        $doc = $this->createDomDocument($page2);
        $xpath = new DOMXPath($doc);
        $rawNodeHeaders = $xpath->query('//div[@class="E05-basic-text"]');

        $headers2Array = [];
        $this->createValidityHeadersArray($rawNodeHeaders, $headers2Array, $xpath);

        $rawProductsRowNodes = $xpath->query('//div[@class="E12-grid-gallery"]');
        $products2Array = [];
        $this->createProductsArray($rawProductsRowNodes, $products2Array, $xpath);

        $this->addToArticleCollection($products2Array, $headers2Array, $cArticles);

        return $this->getResponse($cArticles, $companyId);
    }

    private function createValidityHeadersArray(DOMNodeList $rawNodeHeaders, array &$headersArray, DOMXPath $xpath)
    {
        $currentYear = date("Y");

        //TODO create headers validations and preg-match

        // takes the resource data from here : https://www.hofer.at/de/angebote/aktionen.html
        foreach ($rawNodeHeaders as $key => $header) {

            $isEndDateAvailable = true;

            // Only header Rows with background color has valid items
            $coloredDealRowNode = $xpath->query($header->getNodePath() . '//div[@style="background-color:"]');
            if ($coloredDealRowNode->length == 1 || empty(trim($header->textContent))){
                continue;
            }

            // first search: for '24.06. - 26.06.2021' with end date
            if (!preg_match('#(?<date>(?<start>\d{2}.\d{2}.)\s*-\s*(?<end>\d{2}.\d{2}.\d{4}))#', $header->textContent, $dateMatch)) {


                $isEndDateAvailable = false;

                // second search: for 'b 17.03.2021' at the end of the line without end date.
                // Uncomment to test here if 1st check fails
//                if (!preg_match('#\w\s*(?<date>\d{2}.\d{2}.\d{4}$)#', trim($header->textContent), $dateMatch)) {
//                    throw new Exception(
//                        'Was not possible to find a start date for product ' .
//                        $header->textContent . PHP_EOL
//                    );
//                }
            }


            if($isEndDateAvailable){
                $headersArray[$key]['start'] = $dateMatch['start'] . $currentYear;
                $headersArray[$key]['end'] = $dateMatch['end'];

                continue;
            }

            $headersArray[$key] = $dateMatch['date'];

        }

    }

    private function createProductsArray(DOMNodeList $rawProductsRowNodes, array &$productsArray, DOMXPath $xpath)
    {
        foreach ($rawProductsRowNodes as $key => $productsRowNode) {
            $rowIndex = $key + 1;

            /** @var DOMElement $productsRowNode */
            // this class has a crazy space at the end -> class="item "
            $itemNodes = $xpath->query($productsRowNode->getNodePath() . '//div[@class="item "]');
            foreach ($itemNodes as $productKey => $itemNode) {
                // product title -->
                $productTitle = $xpath->query(
                    $itemNode->getNodePath() . '//figure/figcaption/h3'
                )->item(0)->textContent;
                if ($productTitle == null) {
                    continue;
                }

                $productsArray[$rowIndex][$productKey]['productTitle'] = $productTitle;

                // product image -->
                $imageNode = $xpath->query(
                    $itemNode->getNodePath() . '//figure/img'
                )->item(0);
                $productsArray[$rowIndex][$productKey]['productImage'] = empty($imageNode) ? null : $imageNode->getAttribute('data-src');

                // product price -->
                $div1 = $xpath->query(
                    $itemNode->getNodePath() . '//figure/figcaption'
                )->item(0)->textContent;

                if (!preg_match('#\s*€\s*(?<int>\d{1,2}|-)(,(?<cents>\d{2}|-))#', $div1, $productPrice)) {
                    $this->_logger->warn(
                        'Skipping Product! Price not found for product title: ' . $productsArray[$key][$productKey]['productTitle']
                    );
                    continue;
                }

                $readyPrice = str_replace('-', '0', $productPrice['int'] . ',' . $productPrice['cents']);
                $productsArray[$rowIndex][$productKey]['productPrice'] = $readyPrice;
            }
        }
    }

    private function addToArticleCollection(
        array $productsArray,
        array $validityHeadersArray,
        Marktjagd_Collection_Api_Article $cArticles
    )

    {
        foreach ($productsArray as  $products) {


            foreach ($products as $product) {
                // small validation before add to collection
                if ($product["productPrice"] == '') {
                    $this->_logger->alert('The product ' . $product["productTitle"] . ' does not have price');
                    continue;
                }
                if ($product["productImage"] == '') {
                    $this->_logger->alert('The product ' . $product["productTitle"] . ' does not have Image');
                    continue;
                }

                $eArticle = new Marktjagd_Entity_Api_Article();

                $eArticle->setArticleNumber(md5($product["productTitle"]))
                    ->setTitle($product["productTitle"])
                    ->setPrice($product["productPrice"])
                    ->setImage($product["productImage"])
                    ->setStart($validityHeadersArray[0]['start'])
                    ->setEnd($validityHeadersArray[0]['end'])
                    ->setVisibleStart($validityHeadersArray[0]['start']);

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
}
