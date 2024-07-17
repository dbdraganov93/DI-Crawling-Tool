<?php
/**
 * Article Crawler für Matratzen Concord (ID: 120)
 */

class Crawler_Company_MatratzenConcord_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.matratzen-concord.de/';
        $searchUrl = $baseUrl . 'feeds/matratzen-concord_datenfeed_de.xml';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#(<item[^>]*>.+?<\/item>)#';
        if (!preg_match_all($pattern, $page, $articleMatches)) {
            throw new Exception($companyId . ': unable to get any articles.');
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($articleMatches[1] as $singleArticle) {
            $xmlArticle = simplexml_load_string(preg_replace(array('#<g:#', '#</g:#'), array('<', '</'), $singleArticle), NULL, LIBXML_NOCDATA);

            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($xmlArticle->id)
                ->setTitle($xmlArticle->title)
                ->setText($xmlArticle->description)
                ->setUrl($xmlArticle->link)
                ->setImage($xmlArticle->image_link)
                ->setPrice(preg_replace('#\s*EUR#', '', $xmlArticle->price))
                ->setSize($xmlArticle->size)
            ->setVisibleStart('20.05.2018');

            $cArticles->addElement($eArticle);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);
    }
}