<?php

/**
 * Article Crawler for KiK (ID: 340)
 */

class Crawler_Company_Kik_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sPage = new Marktjagd_Service_Input_Page();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleFile) {
            $pattern = '#artikelliste[^\.]+\.xlsx?#i';
            if (preg_match($pattern, $singleFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        $aData = $sPss->readFile($localArticleFile)->getElement(0)->getData();

        $tracking = '';
        $aArticleUrls = [];
        foreach ($aData as $singleRow) {
            if (!strlen($tracking)) {
                foreach ($singleRow as $singleField) {
                    if (preg_match('#^\?.+#', $singleField)) {
                        $tracking = $singleField;
                        break;
                    }
                }
            }
            if (strlen($singleRow[1]) && preg_match('#^https#', $singleRow[1])) {
                $aArticleUrls[] = $singleRow[1];
            }
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aArticleUrls as $singleArticleUrl) {
            $sPage->open($singleArticleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="product-essential"[^>]*>\s*(.+?)\s*<div[^>]*id="add-to-cart-box"#';
            if (!preg_match($pattern, $page, $articleInfoListMatch)) {
                $this->_logger->err($companyId . ': unable to get article info list: ' . $singleArticleUrl);
                continue;
            }

            $pattern = '#<h1[^>]*class="product-infos__name--title">\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $articleInfoListMatch[1], $titleMatch)) {
                $this->_logger->err($companyId . ': unable to get article title.');
                continue;
            }

            $eArticle = new Marktjagd_Entity_Api_Article();

            $pattern = '#<img[^>]*id="product-image"[^>]*src="([^"]+?)"#';
            if (preg_match($pattern, $articleInfoListMatch[1], $imageMatch)) {
                $eArticle->setImage('https:' . $imageMatch[1]);
            }

            $pattern = '#"price"\s*:\s*"([^"]+?)"#';
            if (preg_match($pattern, $page, $priceMatch)) {
                $eArticle->setPrice($priceMatch[1]);
            }

            $pattern = '#Produktbeschreibung(.+?)<\/ul#';
            if (preg_match($pattern, $articleInfoListMatch[1], $textListMatch)) {
                $pattern = '#<li[^>]*>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $textListMatch[1], $textMatches)) {
                    $eArticle->setText(implode('<br/>', $textMatches[1]));
                }
            }

            $pattern = '#-([^-\.]+?)\.html#';
            if (preg_match($pattern, $singleArticleUrl, $articleNumberMatch)) {
                $eArticle->setArticleNumber($articleNumberMatch[1]);
            }

            $pattern = '#<span[^>]*class="role_product-color-headline"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match_all($pattern, $articleInfoListMatch[1], $colorMatches)) {
                $eArticle->setColor(implode(', ' , $colorMatches[1]));
            }

            $eArticle->setTitle($titleMatch[1])
                ->setUrl($singleArticleUrl . $tracking)
                ->setStart(date('d.m.Y', strtotime('monday next week')))
                ->setEnd(date('d.m.Y', strtotime('saturday next week')))
                ->setVisibleStart($eArticle->getStart());

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }
}