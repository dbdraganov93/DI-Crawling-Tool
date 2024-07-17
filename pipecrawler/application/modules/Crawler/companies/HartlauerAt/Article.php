<?php

/* 
 * Artikel - Crawler für Hartlauer (AT) (ID: 73468)
 */

class Crawler_Company_HartlauerAt_Article extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $cArticles = new Marktjagd_Collection_Api_Article();

        $searchUri = "https://www.hartlauer.at/index.php?filterOutlet=Outlet&cl=search&lang=0&searchparam=*&_artperpage=1500";
        $artLinks = $sPage->getDomElsFromUrl($searchUri, 'img-thumbnail', 'class', 'a');

        $i = 0;
        foreach ($artLinks as $aLink) {
            $dArticle = $sPage->getDomElFromUrlByID($aLink->getAttribute('href'), 'productinfo');
            $eArticle = new Marktjagd_Entity_Api_Article();
            if (preg_match('#\d+#', trim($sPage->getDomElsFromDomEl($dArticle, 'artnum')[0]->textContent), $artNr)) {
                $eArticle->setArticleNumber($artNr[0]);
            }

            if (is_object($oImg = $sPage->getDomElsFromDomEl($dArticle, 'img-thumbnail', 'class', 'a')[0])) {
                $eArticle->setImage($oImg->getAttribute('href'));
            }

            $eArticle->setTitle(trim($sPage->getDomElsFromDomEl($dArticle, 'productTitle', 'id')[0]->textContent))
                ->setText(trim($sPage->getDomElsFromDomEl($dArticle, 'description-content', 'id', 'div')[0]->textContent))
                ->setPrice(trim($sPage->getDomElsFromDomEl($dArticle, 'price', 'itemprop', 'span')[0]->textContent))
                ->setSuggestedRetailPrice(trim(preg_replace('#statt\s*#', '', $sPage->getDomElsFromDomEl($dArticle, 'altPrice', 'class', 'span')[0]->textContent)))
                ->setUrl($aLink->getAttribute('href'));

            $cArticles->addElement($eArticle, TRUE, 'simple', FALSE);
            $this->metaLog(++$i . " von " . count($artLinks) . " erledigt");
        }

        return $this->getResponse($cArticles, $companyId);
    }
}