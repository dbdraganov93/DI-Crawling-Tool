<?php

/* 
 * Artikel Crawler für Möbel Mit (ID: 69741)
 */

class Crawler_Company_MoebelMit_Article extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://sale.planungswelten.de/';
        $searchUrl = $baseUrl . 'fmp/moebel-mit?ajax=1&fi=16255%2C17232%2C24780%2C28192%2C30378%2C30379%2C30380%2C16257&by=date_input&pa=';
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        
        $cStores = $sApi->findStoresByCompany($companyId);
        
        $aStores = array();
        foreach ($cStores->getElements() as $eStore) {
            $aStores[$eStore->getZipcode()] = $eStore->getStoreNumber();
        }
        
        $pageContent = TRUE;
        $siteNo = 0;
        $aArticleNumbers = array();
        while ($pageContent) {
            $sPage->open($searchUrl . $siteNo++);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<li[^>]*class="product"[^>]*id="([^"]+?)"#s';
            if (!preg_match_all($pattern, $page, $articleNumberMatches)) {
                $this->_logger->info($companyId . ': last page reached - ' . ($siteNo - 2));
                $pageContent = FALSE;
            }
            
            $aArticleNumbers = array_merge($aArticleNumbers, $articleNumberMatches[1]);
        }
        
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aArticleNumbers as $singleArticleNumber) {
            $articleDetailUrl = $baseUrl . 'a/' . $singleArticleNumber;
            
            $sPage->open($articleDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*class="offline[^"]*btn">\s*Verkauft\s*\!\s*<#';
            if(preg_match($pattern, $page)) {
                continue;
            }
            
            $pattern = '#<span[^>]*itemprop="([^"]+?)"[^>]*>\s*(.+?)\s*</span#';
            if (!preg_match_all($pattern, $page, $articleInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get any article infos: ' . $articleDetailUrl);
                continue;
            }
            
            $aInfos = array_combine($articleInfoMatches[1], $articleInfoMatches[2]);
            
            $eArticle = new Marktjagd_Entity_Api_Article();
            
            $pattern = '#class="reg_price"[^>]*>[^<]*<s[^>]*>\s*([^<]+?)\s*\€\s*<#';
            if (preg_match($pattern, $page, $suggestedRetailPriceMatch)) {
                $eArticle->setSuggestedRetailPrice(preg_replace(array('#\s+€#', '#\.#', '#,-#'), '', $suggestedRetailPriceMatch[1]));
            }
            
            $pattern = '#itemprop="image"[^>]*src="\/([^"]+?)"#';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eArticle->setImage($baseUrl . $imageMatch[1]);
            }
            
            $pattern = '#<h1[^>]*itemprop="name"[^>]*[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $titleMatch)) {
                $this->_logger->err($companyId . ': unable to get article title: ' . $articleDetailUrl);
                continue;
            }
            
            $eArticle->setTitle($titleMatch[1])
                    ->setArticleNumber($aInfos['productID'])
                    ->setManufacturer($aInfos['manufacturer'])
                    ->setText($aInfos['description'])
                    ->setPrice(preg_replace(array('#\s+€#', '#\.#', '#,-#'), '', $aInfos['price']))
                    ->setUrl($articleDetailUrl)
                    ->setStoreNumber($aStores[$aInfos['postalCode']])
                    ->setVisibleStart('01.11.2017');
            
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}