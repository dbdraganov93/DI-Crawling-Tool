<?php

/**
 * Artikel Crawler für Möbel Schulenburg (ID: 28274)
 */
class Crawler_Company_MoebelSchulenburg_Article extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.moebel-schulenburg.de/';
        $searchUrl = $baseUrl . 'produkte/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="/produkte/"[^>]*>(.+?)</ul#s';
        if (!preg_match($pattern, $page, $productListMatch)) {
            throw new Exception($companyId . ': unable to get product list.');
        }

        $pattern = '#href="/produkte/([^"]+?)"#';
        if (!preg_match_all($pattern, $productListMatch[1], $categories)) {
            throw new Exception($companyId . ': unable to get any categories');
        }
        foreach ($categories[1] as $singleCategory) {
            $sPage->open($searchUrl . $singleCategory);

            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#href="/produkte/(' . $singleCategory . 'seite:.+?)/"#';
            if (preg_match_all($pattern, $page, $additionalSites)) {
                foreach ($additionalSites[1] as $singleAdditionalSite) {
                    if (!in_array($singleAdditionalSite, $categories[1])) {
                        $categories[1][] = $singleAdditionalSite;
                    }
                }
            }
        }
        
        $aArticles = array();

        foreach ($categories[1] as $singleCategory) {
            $sPage->open($searchUrl . $singleCategory);

            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#location\.href=\'/produkte/(details[^\']+?)\'#';
            if (!preg_match_all($pattern, $page, $articleMatches)) {
                $this->_logger->err($companyId . ': unable to find any articles for ' . $singleCategory);
                continue;
            }
            
            foreach ($articleMatches[1] as $singleMatch) {
                $aArticles[] = $singleMatch;
            }
        }
        
        $cArticles = new Marktjagd_Collection_Api_Article();        
        foreach ($aArticles as $singleArticle) {
            $sPage->open($searchUrl . $singleArticle);

            $page = $sPage->getPage()->getResponseBody();
            
            $eArticle = new Marktjagd_Entity_Api_Article();
            
            $pattern = '#<div[^>]*class="produktbild"[^>]*>\s*<a[^>]*href="\s*([^"]+?)\s*"#s';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eArticle->setImage($imageMatch[1]);
            }
            
            $pattern = '#Marke:\s*</h3>\s*<strong[^>]*>\s*(.+?)\s*</strong#s';
            if (preg_match($pattern, $page, $manufacturerMatch)) {
                $eArticle->setManufacturer($manufacturerMatch[1]);
            }
            
            $pattern = '#<div[^>]*class="beschreibung">\s*<h[^>]*>\s*(.+?)\s*</h#s';
            if (!preg_match($pattern, $page, $titleMatch)) {
                $this->_logger->err($companyId . ': unable to get title for ' . $singleArticle);
                continue;
            }
            
            $strText = '';
            $pattern = '#Beschreibung:(.+?)</tr>#s';
            if (preg_match($pattern, $page, $textMatch)) {
                $strText = strip_tags(preg_replace('#\s*<br[^>]*>\s*#', "\n", $textMatch[1]));
            }
            
            $pattern = '#Abmessungen:(.+?)</tr>#s';
            if (preg_match($pattern, $page, $textMatch)) {
                $eArticle->setSize(trim(strip_tags($textMatch[1])));
            }
            
            $pattern = '#(Hinweis:.+?)</tr>#s';
            if (preg_match($pattern, $page, $textMatch)) {
                if (strlen($strText)) {
                    $strText .= "\n\n";
                }
                $strText .= preg_replace(array('#\s{2,}#', '#:#'), array('', ': '), strip_tags($textMatch[1]));
            }
            
            $pattern = '#Art\.\s*Nr\.:\s*(.+?)\s*</tr>#s';
            if (preg_match($pattern, $page, $textMatch)) {
                $eArticle->setArticleNumber(preg_replace('#\s{1,}#', '', strip_tags($textMatch[1])));
            }
            
            $pattern = '#Preis:\s*(.+?)\s*</tr>#s';
            if (preg_match($pattern, $page, $textMatch)) {
                if (preg_match('#([0-9]+|-)\.([0-9]+|-)#', strip_tags($textMatch[1]), $priceMatch)) {
                    $eArticle->setPrice(preg_replace('#\-#', '00', $priceMatch[1] . '.' . $priceMatch[2]));
                }
            }
            
            $eArticle->setTitle($titleMatch[1])
                    ->setText($strText);
            
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
