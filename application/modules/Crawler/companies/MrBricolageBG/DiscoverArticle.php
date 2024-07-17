<?php
/**
 * Discover Crawler for MrBricolage BG (ID: 80637 )
 */


class Crawler_Company_MrBricolageBg_DiscoverArticle extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {

        $campaigns = [
            0 => [
                'url' => '1LGeB7OnvBzuxxVZHRsu1YNVoVxjWXudCorJ0EM1vpFk',
                'name' => 'Бриколаж онлайн промоция валидна до 16.03.2022',
                'start' => '03.03.2022',
                'end' => '16.03.2022 23:59:00',
                'brochure_number'=> 'DC_BricBG_2',

            ],

        ];

        $cArticles = new Marktjagd_Collection_Api_Article();
        $sGS = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        foreach ($campaigns as $campaign) {

            $aData = $sGS->getFormattedInfos($campaign['url'], 'A1', 'P' ,'Discover Product List Bricolage');

            foreach ($aData as $product) {

                $category = '';
                if(isset($product['text'])) {
                    $category = $product['text'] ;

                }

              $eArticle = new Marktjagd_Entity_Api_Article();

                $eArticle->setArticleNumber($campaign['brochure_number'] . '_' . $product['article_number'])
                    ->setTitle($product['title'])
                    ->setText($product['text'])
                    ->setSuggestedRetailPrice($product['suggested_retail_price'])
                    ->setPrice($product['unit_price'])
                    ->setSize($product['unit'])
                    ->setAmount($product['discount'])
                   ->setUrl($product['url'] . $campaign['tracking'])
                    ->setImage($product['image_link'])
                    ->setText($category)
                    ->setStart($campaign['start'])
                    ->setEnd($campaign['end'])
                    ->setVisibleStart($campaign['start']);


               $cArticles->addElement($eArticle);
            }
        }
        return $this->getResponse($cArticles, $companyId);
    }

}

