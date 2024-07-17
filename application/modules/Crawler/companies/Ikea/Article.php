<?php

/*
 * Artikel Crawler für IKEA (ID: 61)
 */

class Crawler_Company_Ikea_Article extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sPage = new Marktjagd_Service_Input_Page();

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $pattern = '#mobile\.xls$#';
        $localXlsFile = '';
        foreach ($sFtp->listFiles('.', $pattern) as $singleFile) {
            $localXlsFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
        }

        $pattern = '#June\.xls$#';
        $localXlsLinkFile = '';
        foreach ($sFtp->listFiles('.', $pattern) as $singleFile) {
            $localXlsLinkFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
        }

        $aData = $sExcel->readFile($localXlsFile, TRUE)->getElement(0)->getData();

        $aArticleUrls = array();
        $pattern = '#url=([^\?]+?catalog[^\?]+?)\?#';
        foreach ($aData as $singleData) {
            if (preg_match($pattern, $singleData['ClickCommand Final'], $urlMatch)) {
                $aArticleUrls[$singleData['clicktag']]['url'] = $urlMatch[1];
                $aArticleUrls[$singleData['clicktag']]['tracking_url'] = $singleData['ClickCommand Final'];
            }
        }

        $aDataLinks = $sExcel->readFile($localXlsLinkFile, TRUE)->getElement(0)->getData();

        $aArticleClickouts = array();
        foreach ($aDataLinks as $singleData) {
            $aArticleClickouts[$singleData['clicktag']]['tracking_url'] = $singleData['ClickCommand Final'];
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aArticleUrls as $key => $singleArticleData) {
            $sPage->open($singleArticleData['url']);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#title[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $titleMatch)) {
                $this->_logger->info($companyId . ': unable to get article title: ' . $singleArticleData['url']);
                continue;
            }

            $pattern = '#<h2>Haupteigenschaften</h2>(.+?)</ul>#s';
            if (!preg_match($pattern, $page, $infoListMatch)) {
                $this->_logger->info($companyId . ': unable to get product infos: ' . $singleArticleData['url']);
                continue;
            }

            $pattern = '#<li[^>]*>\s*([^<]+?)\s*</li>#';
            if (!preg_match_all($pattern, $infoListMatch[1], $infoMatches)) {
                $this->_logger->info($companyId . ': unable to get product infos: ' . $singleArticleData['url']);
            }

            $eArticle = new Marktjagd_Entity_Api_Article();

            $pattern = '#<img[^>]*data-fullImage="([^"]+?)"#';
            if (preg_match_all($pattern, $page, $imageMatches)) {
                $eArticle->setImage(implode(',', $imageMatches[1]));
            }

            $pattern = '#itemprop="lowPrice"[^>]*>(\s*<[^>]*>\s*)*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $priceMatch)) {
                $eArticle->setPrice(preg_replace('#(\s*\/.+)#', '', $priceMatch[2]));

                $pattern = '#itemprop="price"[^>]*>(\s*<[^>]*>\s*)*([^<]+?)\s*<#';
                if (preg_match($pattern, $page, $priceMatch)) {
                    $eArticle->setSuggestedRetailPrice(preg_replace('#(\s*\/.+)#', '', $priceMatch[2]));
                }
            } else {
                $pattern = '#itemprop="price"[^>]*>(\s*<[^>]*>\s*)*([^<]+?)\s*<#';
                if (preg_match($pattern, $page, $priceMatch)) {
                    $eArticle->setPrice(preg_replace('#(\s*\/.+)#', '', $priceMatch[2]));
                }
            }



            $pattern = '#itemprop="productID"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $articleNumberMatch)) {
                $eArticle->setArticleNumber($articleNumberMatch[1]);
            }

            $eArticle->setTitle(preg_replace('#(\s*-\s*)+\s*(IKEA)#', '', $titleMatch[1]))
                ->setUrl('https://redirect.offerista.com/?desktop=' . urlencode($aArticleClickouts[$key]['tracking_url']) . '&mobile=' . urlencode($aArticleUrls[$key]['tracking_url']))
                ->setVisibleStart('08.07.2017')
                ->setVisibleEnd('23.07.2017')
                ->setText(implode('<br/>', $infoMatches[1]));

            $cArticles->addElement($eArticle);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
