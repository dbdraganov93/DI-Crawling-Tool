<?php

/**
 * Discover Crawler for ParkMart BG (ID: 81412 )
 */

class Crawler_Company_ParkmartBG_DiscoverArticle extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {

        $campaigns = [
            0 => [
                'url' => '1Kg0bqiGFWfnIH5Cvu3IEX0Ukp8y-xMERxhsmUIP3vpU',
                'name' => 'Discover Product List Example',
                'start' => '15.12.2021',
                'brochure_number'=> 'DC_1_var',
                 'end' => '31.12.2021 23:59:00'
            ],
            1 => [
                'url' => '1Jff-JtsOiGn60tCFhAR0GOYOX67HMTR5tCeow1VraUE',
                'name' => 'Discover Product List Example',
                'start' => '15.12.2021',
                'brochure_number'=> 'DC_2_sof',
                'end' => '31.12.2021 23:59:00'
            ],
            2 => [
                'url' => '1TDPchZR3aE9c5dqNNlLh5cjgJVydn7zskTVit_rIZMA',
                'name' => 'Discover Product List Example',
                'start' => '15.12.2021',
                'brochure_number'=> 'DC_3_bur',
                'end' => '31.12.2021 23:59:00'
            ]
        ];

        $cArticles = new Marktjagd_Collection_Api_Article();
        $sGS = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        foreach ($campaigns as $campaign) {

            $aData = $sGS->getFormattedInfos($campaign['url'], 'A1', 'P' ,'Discover Product List Example');

            foreach ($aData as $product) {
                if(isset($product['sale_price']) && $product['sale_price'] < $product['price']) {
                    $price = $product['sale_price'] ;
                    $suggestedPrice = $product['price'];
                }
                else {
                    $price = $product['price'] ;
                    $suggestedPrice = NULL;
                }

                # fix missing file extension in image link
                if(!strpos($product['image_link'], '.jpg?context='))
                    $product['image_link'] = str_replace('?context=', '.jpg?context=', $product['image_link']);


                $eArticle = new Marktjagd_Entity_Api_Article();


                $eArticle->setArticleNumber($campaign['brochure_number'] . '_' . $product['article_number'])
                    ->setTitle($product['title'])
                    ->setText($product['text'])
                    ->setSuggestedRetailPrice($product['suggested_retail_price'])
                    ->setPrice($price)
                    ->setSize($product['unit'])
                    ->setAmount($product['discount'])
                    ->setUrl($product['url'] . $campaign['tracking'])
                    ->setImage($product['image'])
                    ->setText($product['category'])
                    ->setStart($campaign['start'])
                    ->setEnd($campaign['end'])
                    ->setVisibleStart($campaign['start']);

                preg_match('#(?<price>.*)/(?<unit>[^)]*)#', $product['extra_referencePrice'], $matches);
                $eArticle->setAdditionalProperties(json_encode(['unit' => ['value' =>  trim($matches['price']), 'unit' => trim($matches['unit'])]]));
                $cArticles->addElement($eArticle);
            }
        }
        return $this->getResponse($cArticles, $companyId);
    }

}
