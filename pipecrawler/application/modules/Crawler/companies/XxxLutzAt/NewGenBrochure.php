<?php

require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Crawler für New Gen Article für XXXLutz AT (ID: 73436 | Stage: 77066)
 */
class Crawler_Company_XxxLutzAt_NewGenBrochure extends Crawler_Generic_Company
{

    protected $_companyId;
    protected $_aNewGenLayout;

    public function crawl($companyId)
    {
        $this->_companyId = $companyId;
        $baseUrl = 'https://digitalesflugblatt.premedia.at/';
        $brochureUrl = $baseUrl . 'api/Publications';
        $articleDetailUrl = $baseUrl . 'api/Documents?publikationId=';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sArchive = new Marktjagd_Service_Input_Archive();

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $cArticles = $sApi->getActiveArticleCollection($this->_companyId);
        foreach ($cArticles->getElements() as $eArticle) {
            if (!preg_match('#-NG#', $eArticle->getArticleNumber())) {
                continue;
            }
            $this->_aNewGenLayout[preg_replace('#-NG#', '', $eArticle->getArticleNumber())] = [
                'id' => $eArticle->getArticleId(),
                'article_number' => $eArticle->getArticleNumber()
            ];
        }

        $sPage->open($brochureUrl);
        $jInfos = $sPage->getPage()->getResponseAsJson();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($jInfos as $singleJInfo) {
            if (strtotime('now') > strtotime($singleJInfo->validTo) || !$singleJInfo->pdfURI) {
                continue;
            }
            $localPath = $sHttp->generateLocalDownloadFolder($companyId);
            $localArchive = $sHttp->getRemoteFile($singleJInfo->pdfURI, $localPath);

            $sArchive->unzip($localArchive, $localPath);
            foreach (scandir($localPath) as $singleFile) {
                if (preg_match('#\.pdf$#', $singleFile)) {
                    $localBrochurePath = $localPath . $singleFile;
                    break;
                }
            }

            $sPage->open($articleDetailUrl . $singleJInfo->id);
            $strArticles = $sPage->getPage()->getResponseBody();
            $strLayout = $this->_generateNewGenLayout($strArticles);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Möbelangebote')
                ->setUrl($localBrochurePath)
                ->setBrochureNumber($singleJInfo->name)
                ->setLayout($strLayout)
                ->setVariety('leaflet');

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    protected function _generateNewGenLayout($strArticles)
    {
        $aData = [];
        $pages = [];
        $jArticles = json_decode($strArticles);
        foreach ($jArticles as $singleJPage) {
            foreach ($singleJPage->artikelOptionen as $singleJArticleList) {
                $category = trim($singleJArticleList->verplanungsinfo->sortiment);
                foreach ($singleJArticleList->hinterlegteArtikelOptionen as $singleJArticle) {
                    if (array_key_exists(trim($singleJArticle->artNr . $singleJArticle->ausfkz), $this->_aNewGenLayout)) {
                        $aData[$category][] = [
                            'articleNumber' => $this->_aNewGenLayout[trim($singleJArticle->artNr . $singleJArticle->ausfkz)]['article_number'],
                            'prio' => random_int(1, 3),
                            'article_id' => $this->_aNewGenLayout[trim($singleJArticle->artNr . $singleJArticle->ausfkz)]['id']];
                        unset($this->_aNewGenLayout[trim($singleJArticle->artNr . $singleJArticle->ausfkz)]);
                    }
                }
            }
        }
        $page = 1;
        foreach ($aData as $category => $aInfos) {
            foreach ($aInfos as $singleArticle) {
                $pages[$page]['articles'][] = [
                    'articleNumber' => $singleArticle['articleNumber'],
                    'prio' => random_int(1, 3),
                    'pageMetaphor' => $category,
                    'article_id' => $singleArticle['article_id']
                ];
            }
            $page++;
        }

        $blender = new Blender('73436');

        return $blender->blend($pages);
    }
}