<?php

/**
 * Discover Crawler für Picnic (ID: 82423)
 */


class Crawler_Company_Picnic_DiscoverArticle extends Crawler_Generic_Company
{
    private string $localBrochurePath;

    public function crawl($companyId)
    {
        $cArticles = new Marktjagd_Collection_Api_Article();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aInfos = $sGSRead->getCustomerData('picnicGer');

        $localPath = $sFtp->connect($companyId . '/Discover', true);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.xml$#', $singleFile)) {
                $this->localBrochurePath = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }
        $sFtp->close();

        $xml = file_get_contents($this->localBrochurePath);
        $data = simplexml_load_string($xml);

        foreach ($data as $item) {

            $singleProduct = [];
            foreach ($item as $key => $value) {
                $singleProduct[(string)$key] = (string)$value;
            }
            $products[] = $singleProduct;
        }
        foreach ($products as $product) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($aInfos['brochureNumber'] . $product['articleNumber'])
                ->setTitle($product['title'])
                ->setText($product['text'])
                ->setImage($product['image1'])
                ->setPrice($product['price'])
                ->setSuggestedRetailPrice($product['suggestedRetailPrice'])
                ->setUrl($product['url'])
                ->setStart($aInfos['validStart'])
                ->setEnd($aInfos['validEnd'])
                ->setVisibleStart($aInfos['validStart']);

            if (isset($product['unit']) && strlen($product['unit'])) {
                $additionalProperties['unit_price'] = ['unit' => $product['unit'], 'value' => $product['price']];
                $eArticle->setAdditionalProperties(json_encode($additionalProperties));
            }

            $cArticles->addElement($eArticle, true, 'complex', false);
        }

        return $this->getResponse($cArticles, $companyId);
    }
}