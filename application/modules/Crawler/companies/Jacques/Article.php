<?php

/* 
 * Artikel Crawler für Jacques' Wein-Depot (ID: 28947)
 */

class Crawler_Company_Jacques_Article extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.jacques.de/';
        $aSearchUrls = array(
            $baseUrl . 'ajax_requests.php?file=teaser&type=selection&tab=Selection_3',
            $baseUrl . 'ajax_requests.php?file=teaser&type=selection_more&tab=Selection_3',
        );        
        $sPage = new Marktjagd_Service_Input_Page();
        
        $aSetInfos = array(
            'name' => 'Title',
            'brand' => 'Trademark',
            'manufacturer' => 'Manufacturer',
            'color' => 'Color',
            'price' => 'Price'
        );
        $aDetailUrls = array();
        foreach ($aSearchUrls as $singleSearchUrl)
        {
            $sPage->open($singleSearchUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<a[^>]*href="([^"]+?)"[^>]*class="textButtons[^"]*Red">Details\s*zum\s*Wein#';
            if(!preg_match_all($pattern, $page, $articleDetailMatches))
            {
                $this->_logger->err($companyId . ': unable to get any article detail urls:' . $singleSearchUrl);
            }
            
            foreach ($articleDetailMatches[1] as $singleDetailUrl)
            {
                $aDetailUrls[] = $singleDetailUrl;
            }
        }
        
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aDetailUrls as $singleArticleDetailUrl)
        {
            $sPage->open($singleArticleDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eArticle = new Marktjagd_Entity_Api_Article();
            
            $pattern = '#itemprop="([^"]+?)">\s*([^<]{4,}?)\s*[<|\€]#';
            if (!preg_match_all($pattern, $page, $articleInfoMatches))
            {
                $this->_logger->err($companyId . ': unable to get any articles infos.');
                continue;
            }
            
            $aInfos = array_combine($articleInfoMatches[1], $articleInfoMatches[2]);
                        
            $pattern = '#img[^>]*src="([^"]+?product_image[^"]+?)"#';
            if (preg_match($pattern, $page, $imageMatch))
            {
                $eArticle->setImage($imageMatch[1]);
            }
            
            $pattern = '#<div[^>]*class="label">\s*([^<]+?)\s*</div>\s*<div[^>]*class="title">\s*([^<]+?)\s*</div>#';
            if (preg_match_all($pattern, $page, $textMatches))
            {
                $strText = '';
                for ($i = 0; $i < count($textMatches[1]); $i++)
                {
                    if (preg_match('#' . $textMatches[1][$i] . '#', $strText))
                    {
                        continue;
                    }
                    if (strlen($strText))
                    {
                        $strText .= '<br/>';
                    }
                    $strText .= $textMatches[1][$i] . ' ' . $textMatches[2][$i];
                }
                $eArticle->setText($strText);
            }
            
            foreach ($aInfos as $infoKey => $infoValue)
            {
                $eArticle->{'set' . $aSetInfos[$infoKey]}($infoValue);
            }
            
            $eArticle->setUrl($singleArticleDetailUrl)
                    ->setStoreNumber('93');
            
            $cArticles->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}