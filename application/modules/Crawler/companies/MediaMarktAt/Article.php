<?php

/**
 * Article Crawler für Media Markt AT (ID: 73214)
 */
class Crawler_Company_MediaMarktAt_Article extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        ini_set('memory_limit', '4G');
        $searchUrl = 'https://transport.productsup.io/c356556d9ccea18d6c6e/channel/321962/at-mm.wogibtswas.csv';
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPSs = new Marktjagd_Service_Input_PhpSpreadsheet();
        $cArticles = new Marktjagd_Collection_Api_Article();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $articlesInSystem = $sApi->findActiveArticlesByCompany($companyId);

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $localArticleFile = $sHttp->getRemoteFile($searchUrl, $localPath);

        $aData = $sPSs->readFile($localArticleFile, true, ',')->getElement(0)->getData();

        $finalStores = [];
        foreach ($aData as $singleStore) {
            // check if available
            if($singleStore['label'] == 1){
                $finalStores[] = $singleStore;
            }
        }

        if(empty($finalStores)) {
            throw new Exception('The crawler was not able to find any Stores');
        }

        foreach ($finalStores as $singleRow) {
            $isDoubledArticle = $this->checkForDoubledArticle($articlesInSystem, $singleRow);
            if($isDoubledArticle) {
                continue;
            }

            $oldUrlParam = '?utm_source=wogibtswas&utm_medium=dis-other%20display&utm_campaign=display%20awareness_mm-ka-produkte%20onl-nat_3222';
            $newUrlParam = '?utm_source=wogibtswas&utm_medium=dis-dynamic%20product%20ad&utm_campaign=display%20awareness_mm-dynamic%20product%20ad-displaydynamic%20product%20ad-_';
            $replacedUrl = str_replace($oldUrlParam, $newUrlParam, $singleRow['url']);

            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setArticleNumber($singleRow['article_number'])
                ->setTitle($singleRow['title'])
                ->setText($singleRow['text'])
                ->setImage($singleRow['image'])
                ->setPrice(str_replace(' EUR', '', $singleRow['price']))
                ->setUrl($replacedUrl)
                ->setEan($singleRow['EAN'])
                ->setStart(date('d.m.Y'))
                ->setVisibleStart($eArticle->getStart())
                ->setEnd(date('d.m.Y', strtotime('+3 days')))
            ;

            if($singleRow['energy_label'] !== 'n/a' && $singleRow['energy_label_new'] !== 'n/a')
                $eArticle->setAdditionalProperties(json_encode(
                    ['energyLabel' => $singleRow['energy_label'], 'energyLabelType' => $singleRow['energy_label_new']]
                ));

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }

    private function checkForDoubledArticle($articlesInSystem, $singleRow): bool
    {
        $response = false;

        foreach ($articlesInSystem as $articleInSystem) {
            if(isset($articleInSystem['title']) &&
                ($articleInSystem['title'] == trim($singleRow['title']))
            ) {
                $response = true;
                break;
            }
        }

        return $response;
    }
}
