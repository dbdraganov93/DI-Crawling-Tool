<?php

/*
 * Artikel Crawler für Naturgut (ID: 385)
 */

class Crawler_Company_Naturgut_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://naturgut.shop';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*id="nav-box-wrapper"[^>]*>(.+?)<div[^>]*class="underdog"#';
        if (!preg_match($pattern, $page, $categoryListMatch)) {
            throw new Exception($companyId . ': unable to get category list.');
        }

        $pattern = '#<a[^>]*(class="title-cat"[^>]*>.+?)<\/ul>\s*<\/div#';
        if (!preg_match_all($pattern, $categoryListMatch[1], $categoryMatches)) {
            throw new Exception($companyId . ': unable to get any categories.');
        }

        $aCategoryUris = array();
        foreach ($categoryMatches[1] as $singleCategory) {
            $pattern = '#title-cat"[^>]*href="https:\/\/naturgut\.shop\/([^"]+?)\/?"#';
            if (!preg_match($pattern, $singleCategory, $mainCategoryMatch)) {
                $this->_logger->err($companyId . ': unable to get main category: ' . $singleCategory);
                continue;
            }

            $pattern = '#<li[^>]*>\s*<a[^>]*href="https:\/\/naturgut\.shop\/([^"]+?)\/?"#';
            if (preg_match_all($pattern, $singleCategory, $subCategoryMatches)) {
                $aCategoryUris = array_merge($aCategoryUris, $subCategoryMatches[1]);
                continue;
            }

            $aCategoryUris[] = $mainCategoryMatch[1];
        }

        $aArticleUris = array();
        foreach ($aCategoryUris as $singleCategoryUri) {
            try {
                $sPage->open($baseUrl . '/' . preg_replace('#\s+#', '%20', $singleCategoryUri) . '/?ldtype=line&_artperpage=100&pgNr=0&cl=alist');
                $page = $sPage->getPage()->getResponseBody();
            } catch (Exception $e) {
                continue;
            }

            $pattern = '#<a[^>]*id="productList[^>]*href="([^"]+?)"[^>]*class="lead#';
            if (!preg_match_all($pattern, $page, $articleUriMatches)) {
                $pattern = '#<li[^>]*class="\s*end\s*level-4"[^>]*>\s*<a[^>]*href="([^"]+?)"#';
                if (!preg_match_all($pattern, $page, $subSubCategoryMatches)) {
                    $this->_logger->err($companyId . ': unable to get any sub categories for category: ' . $singleCategoryUri);
                    continue;
                }

                foreach ($subSubCategoryMatches[1] as $singleSubCategoryUri) {
                    try {
                        $sPage->open(preg_replace('#\s+#', '%20', $singleSubCategoryUri) . '?ldtype=line&_artperpage=100&pgNr=0&cl=alist');
                        $page = $sPage->getPage()->getResponseBody();
                    } catch (Exception $e) {
                        continue;
                    }

                    $pattern = '#<a[^>]*id="productList[^>]*href="([^"]+?)"[^>]*class="lead#';
                    if (preg_match_all($pattern, $page, $articleUriMatches)) {
                        $aArticleUris = array_merge($aArticleUris, $articleUriMatches[1]);
                        continue;
                    }
                    break 2;
                }
            }

            $aArticleUris = array_merge($aArticleUris, $articleUriMatches[1]);
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aArticleUris as $singleArticleUri) {
            $sPage->open($singleArticleUri);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="detailsInfo\s*clear"[^>]*>(.+?)<div[^>]*class="tobasket"#';
            if (!preg_match($pattern, $page, $articleInfoListMatch)) {
                $this->_logger->err($companyId . ': unable to get article info list: ' . $singleArticleUri);
                continue;
            }

            $pattern = '#<link[^>]*itemprop="availability"[^>]*href="http:\/\/schema.org\/InStock"#';
            if (!preg_match($pattern, $articleInfoListMatch[1], $availabilityMatch)) {
                $this->_logger->info($companyId . ': article not available: ' . $singleArticleUri);
                continue;
            }

            $pattern = '#span[^>]*itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $articleInfoListMatch[1], $articleInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get any article infos from list: ' . $singleArticleUri);
                continue;
            }

            $aArticleInfos = array_combine($articleInfoMatches[1], $articleInfoMatches[2]);

            $eArticle = new Marktjagd_Entity_Api_Article();

            $pattern = '#<div[^>]*class="fake-cell"[^>]*>\s*<img[^>]*data-src="([^"]+?\.jpg)"#';
            if (preg_match($pattern, $articleInfoListMatch[1], $imageMatch)) {
                $eArticle->setImage($imageMatch[1]);
            }

            $pattern = '#<p[^>]*class="shortDescription[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $articleInfoListMatch[1], $textMatch)) {
                $eArticle->setText($textMatch[1]);
            }

            $pattern = '#<div[^>]*class="artNum"[^>]*>\s*ArtNr\s*\.?\s*:\s*(\d+)\s*<#i';
            if (preg_match($pattern, $articleInfoListMatch[1], $articleNumberMatch)) {
                $eArticle->setArticleNumber($articleNumberMatch[1]);
            }

            $eArticle->setTitle($aArticleInfos['name'])
                ->setPrice(preg_replace('#\s+\€#', '', $aArticleInfos['price']))
                ->setUrl($singleArticleUri);

            $cArticles->addElement($eArticle);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
