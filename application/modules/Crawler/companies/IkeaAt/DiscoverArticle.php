<?php

/**
 * Discover Crawler für Ikea AT (ID: 73466)
 */

class Crawler_Company_IkeaAt_DiscoverArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # adjust the campaign array when a new campaign starts                  #
        # update the articles daily -> Ike renews the feed every day            #
        #                                                                       #
        #########################################################################

        $campaigns = [
            1 => [
                'article_url' => 'pup-feed.csv',
                'start_date' => '14.11.2023',
                'end_date' => '06.12.2023',
            ],
        ];

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $sFtp->connect($companyId);
        $sFtp->changedir('Discover');


        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($campaigns as $campaign) {

            $localArticleFile = $sFtp->downloadFtpToDir($campaign['article_url'], $localPath);
            $aData = $sPss->readFile($localArticleFile, TRUE, "\t")->getElement(0)->getData();

            foreach ($aData as $singleRow) {
                # extract URL, image links, prices and product category
                $price = trim(preg_replace('#eur#i', '', $singleRow['price']));
                $salePrice = trim(preg_replace('#eur#i', '', $singleRow['sale_price'])) ?: null;
                $url = $singleRow['link'];
                $images = $singleRow['additional_image_link'] ? $singleRow['image_link'] . ", " . $singleRow['additional_image_link'] : $singleRow['image_link'];

                $eArticle = new Marktjagd_Entity_Api_Article();
                $eArticle->setArticleNumber('DISCOVER_' . $singleRow['id'] . '_' . $campaign['start_date'])
                    ->setTitle($singleRow['title'])
                    ->setText($singleRow['description'])
                    ->setPrice($salePrice ?? $price)
                    ->setSuggestedRetailPrice($salePrice ? $price : null)
                    ->setArticleNumberManufacturer($singleRow['mpn'])
                    ->setImage($images)
                    ->setUrl('https://track.adform.net/C/?bn=69439523;gdpr=${gdpr};gdpr_consent=${gdpr_consent_50};cpdir=' . $url)
                    ->setTrademark($singleRow['brand'])
                    ->setTags($singleRow['product_type'])
                    ->setColor($singleRow['color'])
                    ->setVisibleStart($campaign['start_date'])
                    ->setVisibleEnd($campaign['end_date'])
                    ->setNational(TRUE);

                $cArticles->addElement($eArticle, TRUE, 'complex', FALSE);
            }
        }
        return $this->getResponse($cArticles, $companyId);
    }
}
