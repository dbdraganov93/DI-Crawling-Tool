<?php

/**
 * Artikel Crawler für Phone House (ID: 28900)
 */
class Crawler_Company_PhoneHouse_Article extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.phonehouse.de/';
        $searchUrl = $baseUrl . 'api/rest.php/article.json';
        $articleUrl = $baseUrl . 'produkt/';
        $validDistribution = "Phone House Shop";
        
        $sPage = new Marktjagd_Service_Input_Page();
        
        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open main page.');
        }
        
        $jArticles = json_decode($sPage->getPage()->getResponseBody());
        
        $aArticleIds = array();
        foreach ($jArticles as $singleArticle) {
            $aArticleIds[$singleArticle->id] = $singleArticle->artnr;
        }
        
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aArticleIds as $articleKey => $articleValue) {
            if (!$sPage->open($articleUrl . $articleValue)) {
                $this->_logger->err($companyId . ': unable to open article detail page. no: ' . $articleValue);
                continue;
            }
            
            $page = $sPage->getPage()->getResponseBody();
            $eArticle = new Marktjagd_Entity_Api_Article();
            
            $pattern = '#<div[^>]*class="HardwareOnly"[^>]*>(.+?<meta[^>]*>)\s*</div#s';
            if (!preg_match($pattern, $page, $detailMatch)) {
                $this->_logger->info($companyId . ': unable to open article details. no: ' . $articleValue);
                continue;
            }
            
            $pattern = '#IconWebExclusiv#';
            if (preg_match($pattern, $detailMatch[1])) {
                $this->_logger->info($companyId . ': web exclusive offer only. no: ' . $articleValue);
                continue;
            }
            
            $pattern = '#itemprop="price"[^>]*content="(.+?)"#';
            if (!preg_match($pattern, $detailMatch[1], $priceMatch)) {
                $this->_logger->err($companyId . ': unable to get price. no: ' . $articleValue);
                continue;
            }
            
            $pattern = '#itemprop="name"[^>]*content="(.+?)"#';
            if (!preg_match($pattern, $detailMatch[1], $nameMatch)) {
                $this->_logger->err($companyId . ': unable to get article name. no: ' . $articleValue);
                continue;
            }
            
            $pattern = '#itemprop="image"[^>]*src="\/(.+?)"#';
            if (!preg_match($pattern, $page, $imageMatch)) {
                $this->_logger->err($companyId . ': unable to get article image. no: ' . $articleValue);
                continue;
            }
            
            $pattern = '#class="Checklist"[^>]*>(.+?)</ul#s';
            if (preg_match($pattern, $page, $attributeMatch)) {
                $pattern = '#<li[^>]*>(.+?)</li#s';
                if (preg_match_all($pattern, $attributeMatch[1], $singleAttributeMatches)) {
                    $strAttributes = '';
                    foreach ($singleAttributeMatches[1] as $singleAttribute) {
                        if (strlen($strAttributes)) {
                            $strAttributes .= '<br>';
                        }
                        $strAttributes .= $singleAttribute;
                    }
                }
            }
                        
            $eArticle->setTitle($nameMatch[1])
                    ->setPrice($priceMatch[1])
                    ->setImage($baseUrl . $imageMatch[1])
                    ->setText($strAttributes)
                    ->setArticleNumber($articleKey)
                    ->setDistribution($validDistribution);
            
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}