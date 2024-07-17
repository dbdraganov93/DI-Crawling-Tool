<?php

/**
 * Discover Crawler für Obi AT (ID: 73321)
 */

class Crawler_Company_ObiAt_DiscoverArticle extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # adjust the campaign array when a new campaign starts                  #
        # update the articles daily -> Obi renews the feed every day            #
        #                                                                       #
        #########################################################################
        $campaigns = [

            47 => [
                'article_url' => 'https://transport.productsup.io/ca804f076538eba52333/channel/415873/offerista_at_mcampaign8030_ferienzeit_juni.csv',
                'start_date' => '08.06.2022',
                'end_date' => '19.06.2022',
            ],

        ];

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $cArticles = new Marktjagd_Collection_Api_Article();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);

        foreach ($campaigns as $campaign) {
            $this->_logger->info($companyId . ': getting ' . $campaign['article_url']);
            $localArticleFile = $sHttp->getRemoteFile($campaign['article_url'], $localPath);

            if (!strlen($localArticleFile)) {
                $this->_logger->err($companyId . ': unable to get feed file: ' . $campaign['article_url']);
                continue;
            }

            $aData = $sPss->readFile($localArticleFile, TRUE, ',')->getElement(0)->getData();

            foreach ($aData as $singleRow) {

                /*
                * We skip products from this category
                * https://redmine.offerista.com/issues/77019
                */
                if ($singleRow['wunschkategorie'] == 'Klima und Insektenschutz') {
                    continue;
                }


                # extract URL, image links, prices and product category
                $price = trim(preg_replace('#eur#i', '', $singleRow['price']));
                $salePrice = trim(preg_replace('#eur#i', '', $singleRow['sale_price'])) ?: null;
                $url = $singleRow['link'];

//                if (preg_match('#\.comc(\d{2})\.#', $url, $campaignNumberMatch) && (int)$campaignNumberMatch[1] >= 27 && (int)$campaignNumberMatch[1] <= 29) {
//                    $campaignNumber = (int)$campaignNumberMatch[1] + 1;
//                    $url = preg_replace('#\.comc(\d{2})\.#', '.comc' . $campaignNumber . '.', $url);
//                }

                $images = $singleRow['additional_image_link'] ? $singleRow['image_link'] . ", " . $singleRow['additional_image_link'] : $singleRow['image_link'];

                $eArticle = new Marktjagd_Entity_Api_Article();
                $eArticle->setArticleNumber('DISCOVER_' . $singleRow['id'])
                    ->setTitle($singleRow['title'])
                    ->setText($singleRow['description'])
                    ->setPrice($salePrice ?? $price)
                    ->setSuggestedRetailPrice($salePrice ? $price : null)
                    ->setArticleNumberManufacturer($singleRow['mpn'])
                    ->setImage($images)
                    ->setUrl($url)
                    ->setTrademark($singleRow['brand'])
                    ->setTags($singleRow['product_type'])
                    ->setColor($singleRow['color'])
                    ->setEan($singleRow['gtin'])
                    ->setStart($campaign['start_date'])
                    ->setEnd($campaign['end_date'])
                    ->setVisibleStart($campaign['start_date'])
                    ->setShipping($singleRow['shipping_price'])
                    ->setAdditionalProperties($this->calculateAdditionalProperties($singleRow));

                $cArticles->addElement($eArticle, TRUE, 'complex', FALSE);
            }
        }
        return $this->getResponse($cArticles, $companyId);
    }

    private function calculateAdditionalProperties($singleRow)
    {
        $additionalProperties = [
            'taxInfo' => 'inkl. gesetzl. MwSt. 20%',
            'shippingInfo' => 'zzgl. Versandkosten'
        ];

        if ($singleRow['basePriceUnit']) {
            $additionalProperties['unitPrice'] = ['value' => $singleRow['basePrice'], 'unit' => $singleRow['basePriceUnit']];
        }

        if ($singleRow['top_price'] == 'ja') {
            $additionalProperties['priceLabel'] = 'Top Preis';
        }

        if ($singleRow['shipping_price'] == 0) {
            $additionalProperties['shippingInfo'] = 'Versandkostenfrei';
        }

        if (!preg_match('#[0|-]#', $singleRow['energy_scale_type'])
            and !preg_match('#[0|-]#', $singleRow['energy_efficiency_class'])
            and !empty($singleRow['energy_scale_type'])
            and !empty($singleRow['energy_efficiency_class'])) {
            $additionalProperties['energyLabelType'] = str_replace(["alt", "neu"], ["old", "new"], strtolower($singleRow['energy_scale_type']));
            $additionalProperties['energyLabel'] = $singleRow['energy_efficiency_class'];
        }

        return json_encode($additionalProperties);
    }
}
