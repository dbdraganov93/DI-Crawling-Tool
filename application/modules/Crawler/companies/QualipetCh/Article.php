<?php

/**
 * Article Crawler für Qualipet CH (ID: 72174)
 */

class Crawler_Company_QualipetCh_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $aUrls = [
            'https://www.qualipet.ch/_exports/google_shopping_de.xml',
            'https://www.qualipet.ch/_exports/google_shopping_fr.xml'
        ];
        $sHttp = new Marktjagd_Service_Transfer_Http();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);

        foreach ($aUrls as $singleUrl) {
            if (preg_match('#_(\w{2})\.xml#', $singleUrl, $languageCodeMatch)) {
                $aLocalArticleFiles[$languageCodeMatch[1]] = $sHttp->getRemoteFile($singleUrl, $localPath);
            }
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aLocalArticleFiles as $languageCode => $localFile) {
            $xmlArticles = simplexml_load_file($localFile)->channel;
            foreach ($xmlArticles->item as $singleXMLArticle) {
                $gData = $singleXMLArticle->children($xmlArticles->getNamespaces(TRUE)['g']);

                if (!preg_match('#in\s*stock#', $gData->availability)) {
                    continue;
                }

                $eArticle = new Marktjagd_Entity_Api_Article();

                $eArticle->setTitle((string)$singleXMLArticle->title)
                    ->setUrl((string)$singleXMLArticle->link . '?utm_source=Profital&utm_campaign=Profital_Product')
                    ->setArticleNumber((string)$gData->id . '-' . $languageCode)
                    ->setManufacturer((string)$gData->brand)
                    ->setEan((string)$gData->gtin)
                    ->setImage((string)$gData->image_link)
                    ->setDistribution($languageCode)
                    ->setLanguageCode($languageCode);

                if ($gData->sale_price) {
                    $eArticle->setPrice(preg_replace('#\s*CHF#', '', (string)$gData->sale_price))
                        ->setSuggestedRetailPrice(preg_replace('#\s*CHF#', '', (string)$gData->price));
                } else  {
                    $eArticle->setPrice(preg_replace('#\s*CHF#', '', (string)$gData->price));
                }

                $cArticles->addElement($eArticle);
            }
        }

        return $this->getResponse($cArticles, $companyId);
    }
}