<?php

/**
 * Artikel Crawler für Real (ID: 15)
 */
class Crawler_Company_Real_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://webservices.real.de/';
        $searchUrl = $baseUrl . 'v3/realecircular?week=';
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sTimes = new Marktjagd_Service_Text_Times();

        $aDates = array(
            date('W', strtotime('this week')) . '-' . $sTimes->getWeeksYear()
        );

        $oPage = $sPage->getPage();
        $oPage->setTimeout('300');
        $sPage->setPage($oPage);

        $cArticles = new Marktjagd_Collection_Api_Article();

        foreach ($aDates as $singleWeek)
        {
            $this->_logger->info('open ' . $searchUrl . $singleWeek);            
            $sPage->open($searchUrl . $singleWeek);
            $page = $sPage->getPage()->getResponseBody();

            if (strlen($page))
            {
                $this->_logger->info($companyId . ': page opened.');
            }

            $pattern = '#<entry[^>]*>\s*(.+?)\s*</entry#';
            if (!preg_match_all($pattern, $page, $articleMatches))
            {
                throw new Exception($companyId . ': unable to get any articles.');
            }

            foreach ($articleMatches[1] as $singleArticle)
            {
                $eArticle = new Marktjagd_Entity_Api_Article();

                $pattern = '#heading_text>\s*(.+?)\s*<#';
                if (!preg_match($pattern, $singleArticle, $titleMatch))
                {
                    continue;
                }

                $pattern = '#offer_id>\s*(.+?)\s*<#';
                if (preg_match($pattern, $singleArticle, $numberMatch))
                {
                    $eArticle->setArticleNumber($numberMatch[1]);
                }

                $pattern = '#store_group>\s*(.+?)\s*<#';
                if (preg_match($pattern, $singleArticle, $storeMatch))
                {
                    $eArticle->setStoreNumber($storeMatch[1]);
                }

                $pattern = '#body_text>\s*(.+?)\s*<#';
                if (preg_match($pattern, $singleArticle, $textMatch))
                {
                    $eArticle->setText($textMatch[1]);
                }

                $pattern = '#ad_price>\s*(.+?)\s*<#';
                if (preg_match($pattern, $singleArticle, $priceMatch))
                {
                    $eArticle->setPrice($priceMatch[1]);
                }

                $pattern = '#<(g:)?image_link>\s*(.+?)\s*<#';
                if (preg_match($pattern, $singleArticle, $imageMatch))
                {
                    $eArticle->setImage($imageMatch[2]);
                }

                $pattern = '#effective_date>\s*(.+?)\s*<#';
                if (preg_match($pattern, $singleArticle, $dateMatch))
                {
                    $aTimes = preg_split('#\/#', $dateMatch[1]);
                    $eArticle->setStart(preg_replace('#T.+#', '', $aTimes[0]))
                            ->setEnd(preg_replace('#T.+#', '', $aTimes[1]))
                            ->setVisibleStart(date('Y-m-d', strtotime($eArticle->getStart() . '-2 days')));
                }

                $pattern = '#landing_page_link.+?cpdir=(http.+?html)#';
                if (preg_match($pattern, $singleArticle, $linkMatch))
                {
//                    $eArticle->setUrl($linkMatch[1]);

                    $sPage->open($linkMatch[1]);
                    $page = $sPage->getPage()->getResponseBody();
                    $pattern = '#og:description"\s*content="([^"]+?)\s*Alle\s*Wochenangebote\s*auf\s*www\.real\.de\s*"#s';
                    if (preg_match($pattern, $page, $textMatch))
                    {
                        $eArticle->setText(preg_replace('#\s{2,}#', '<br/>', $textMatch[1]));
                    }
                }

                $eArticle->setTitle($titleMatch[1]);

                if (!strlen($eArticle->getPrice())) {
                    continue;
                }
                if($cArticles->addElement($eArticle)) {
                    $this->_logger->info($companyId . ': article added.');
                }
                if (count($cArticles->getElements()) == 50) {
                    break;
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
