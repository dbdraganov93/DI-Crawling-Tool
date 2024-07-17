<?php

/**
 * Discover Crawler for Zurbrueggen (ID: 68757 )
 */

class Crawler_Company_Zurbrueggen_DiscoverArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)


    {

        $cArticles = new Marktjagd_Collection_Api_Article();
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $campaigns = [
            0 => [
                'url' => 'https://www.semtrack.de/e?i=946e7c2fa6294b47b354eae4e00c67ba9c56412b',
                'tracking' => '?utm_source=offerista%20discover&utm_medium=feed&utm_campaign=Angebote%20aus%20den%20Prospekten',
                'start' => '14.11.2022',
                'end' => '31.12.2022 23:59:59',
            ],
        ];

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $cStores = $sApi->findStoresByCompany($companyId);
        $aStoreNumbers = [];
        foreach ($cStores->getElements() as $eStore) {
            $aStoreNumbers[] = $eStore->getStoreNumber();

            }

        foreach ($campaigns as $campaign) {

            $sPage->getPage()->setIgnoreRobots(TRUE);
            $sPage->open($campaign['url']);

            $generatePath = $sFtp->generateLocalDownloadFolder($companyId);

            $localArticleFile = $sHttp->getRemoteFile($campaign['url'], $generatePath);
            $aData = $sPss->readFile($localArticleFile, TRUE, ';')->getElement(0)->getData();

            foreach ($aData as $product) {


                $eArticle = new Marktjagd_Entity_Api_Article();

                if (isset($product['sale_price']) && $product['sale_price'] < $product['price']) {
                    $price = $product['sale_price'];
                    $suggestedPrice = $product['price'];
                } else {
                    $price = $product['price'];
                    $suggestedPrice = NULL;
                }

                //remove double commas from the images if exists, and add multiple images if exists.
                $imageArray = [];
                if (strlen($product['image_link1'])) {
                    $imageArray[] = $product['image_link1'];
                }
                if (strlen($product['image_link2'])) {
                    $imageArray[] = $product['image_link2'];
                }
                if (strlen($product['image_link3'])) {
                    $imageArray[] = $product['image_link3'];
                }

                //logic for a second query param one exist
                $queryParam = '';
                if (strpos($product['link'], '?') !== false) {
                    $queryParam = str_replace('?', '&', $campaign['tracking']);
                }
                if (strpos($product['link'], '?') == false) {
                    $queryParam = $campaign['tracking'];
                }

                $eArticle->setArticleNumber('DISCOVER_' . $product['id'])
                    ->setTitle($product['title'])
                    ->setText($product['description'])
                    ->setPrice($suggestedPrice ?? $price)
                    ->setSuggestedRetailPrice($suggestedPrice ? $price : null)
                    ->setAmount($product['availability'])
                    ->setTrademark($product['brand'])
                    ->setEan($product['gtin'])
                    ->setArticleNumberManufacturer($product['mpn'])
                    ->setTags($product['section'])
                    ->setStoreNumber(implode(',', $aStoreNumbers))
                    ->setText($product['description'])
                    ->setUrl($product['link'] . $queryParam)
                    ->setImage(implode(',', $imageArray))
                    ->setStart($campaign['start'])
                    ->setEnd($campaign['end'])
                    ->setVisibleStart($campaign['start']);
//                    ->setAdditionalProperties($product['section_prio']);

                $cArticles->addElement($eArticle);

                $cArticles->addElement($eArticle, true, 'complex', false);
            }
        }
//        Zend_Debug::dump($eArticle);die();
        return $this->getResponse($cArticles, $companyId);
    }
}
