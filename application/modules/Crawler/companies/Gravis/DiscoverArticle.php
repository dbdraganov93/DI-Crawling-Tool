<?php

/**
 * Discover article crawler for Gravis (ID: 29034)
 */

class Crawler_Company_Gravis_DiscoverArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sGsRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $aInfos = $sGsRead->getCustomerData('gravisGer');

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles('./discover') as $singleRemoteFile) {
            if (preg_match('#' . $aInfos['articleFileName'] . '#', $singleRemoteFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localArticleFile, TRUE)->getElement(0)->getData();

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $aArticle) {
            if (!preg_match('#\d+#', $aArticle['articleNumber'])) {
                continue;
            }

            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($aArticle['articleNumber'] . '_' . $aInfos['brochureNumber'])
                ->setTitle($aArticle['title'])
                ->setText($aArticle['text'])
                ->setImage($aArticle['image1'])
                ->setUrl($aArticle['url'])
                ->setPrice($aArticle['price'])
                ->setStart($aInfos['validityStart'])
                ->setEnd($aInfos['validityEnd'])
                ->setVisibleStart($eArticle->getStart());

            if (strlen($aArticle['suggestedRetailPrice'])) {
                $eArticle->setPrice($aArticle['suggestedRetailPrice'])
                    ->setSuggestedRetailPrice($aArticle['price']);
            }

            if (strlen($aArticle['energyLabel'])) {
                $strAdditionalAttributes = json_encode(['energyLabel' => $aArticle['EnergyLabel'], 'energyLabelType' => 'new']);

                $eArticle->setAdditionalProperties($strAdditionalAttributes);
            }

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles);
    }
}