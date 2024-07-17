<?php

/*
 * Artikel Crawler für AWG (ID: 84)
 */

class Crawler_Company_AWG_Article extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sPage = new Marktjagd_Service_Input_Page();
        $sFtp->connect($companyId);
        $localArticleFile = '';
        $pattern = '#Produktdaten\.csv#';

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match($pattern, $singleFile, $articleFileMatch)) {
                $localPath = $sFtp->generateLocalDownloadFolder($companyId);
                $localArticleFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        $aArticleData = $sExcel->readFile($localArticleFile, TRUE, ';')->getElement(0)->getData();
        $cArticles = new Marktjagd_Collection_Api_Article();

        foreach ($aArticleData as $singleArticle) {
            $strArticleUrl = preg_replace('#(.+?\.html).+#', '$1', $singleArticle['url']);

            $sPage->open($strArticleUrl);
            $page = $sPage->getPage()->getResponseBody();

            if (preg_match('#<h1>.+?Treffer f.+?r#', $page)) {
                $pattern = '#<a[^>]*href="([^"]+)"[^>]*itemprop="url"[^>]*>\s*<img#';
                if (preg_match($pattern, $page, $matchPageUrl)) {
                    $sPage->open($matchPageUrl[1]);
                    $page = $sPage->getPage()->getResponseBody();
                }
            }

            $pattern = '#(<[^>]*itemprop="name"[^>]*>.+?)Material#';
            if (!preg_match($pattern, $page, $articleAttributeListMatch)) {
                $this->_logger->err($companyId . ': unable to get article attribute list: ' . $strArticleUrl);
                continue;
            }

            $pattern = '#<[^>]*itemprop="([^"]+?)"[^>]*>\s*(<[^>]*>\s*)*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $articleAttributeListMatch[1], $articleAttributeMatches)) {
                $this->_logger->err($companyId . ': unable to get any article attributes: ' . $strArticleUrl);
                continue;
            }

            $aAttributes = array_combine($articleAttributeMatches[1], $articleAttributeMatches[3]);

            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setTitle($aAttributes['name'])
                    ->setArticleNumber(preg_replace('#Artikel-Nr\.:\s*(.+)#', '$1', $aAttributes['sku']))
                    ->setPrice($aAttributes['offers'])
                    ->setUrl($strArticleUrl);

            if (array_key_exists('price', $aAttributes)) {
                $eArticle->setSuggestedRetailPrice(preg_replace('#\s*\€#', '', $aAttributes['offers']))
                        ->setPrice($aAttributes['price']);
            }
            
            $pattern = '#<[^>]*headline[^>]*>\s*Farbe\s*<[^>]*>(.+?)</ul#';
            if (preg_match($pattern, $articleAttributeListMatch[1], $colorListMatch)) {
                $pattern = '#<a[^>]*href[^>]*title="([^"]+?)"#';
                if (preg_match_all($pattern, $colorListMatch[1], $colorMatches)) {
                    $eArticle->setColor(implode(', ', array_unique($colorMatches[1])));
                }
            }
            
            $pattern = '#<[^>]*headline[^>]*>\s*Größe\s*<[^>]*>(.+?)</ul#';
            if (preg_match($pattern, $articleAttributeListMatch[1], $sizeListMatch)) {
                $pattern = '#<span[^>]*>\s*(\d{2}|[A-Z]{1,3})\s*<#';
                if (preg_match_all($pattern, $sizeListMatch[1], $sizeMatches)) {
                    $eArticle->setSize(implode(', ', $sizeMatches[1]));
                }
            }
            
            $pattern = '#<h2[^>]*longdesc[^>]*>\s*Artikelbeschreibung\s*<[^>]*>(.+?)</ul#';
            if (preg_match($pattern, $articleAttributeListMatch[1], $descListMatch)) {
                $pattern = '#<li[^>]*>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $descListMatch[1], $descMatches)) {

                    $eArticle->setText(implode('<br/>', $descMatches[1]));
                }
            }

            $text = $eArticle->getText();
            if (strlen($text)) {
                $text .= '<br><br>';
            }

            $text .= 'Der Onlinepreis auf awg-mode.de kann abweichen';
            $eArticle->setText($text);
            
            $pattern = '#<a[^>]*href="([^"]+?product\/pdpzoom[^"]+?)"#';
            if (preg_match_all($pattern, $page, $imageMatches)) {
                $eArticle->setImage(implode(',', $imageMatches[1]));
            }

            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
