<?php

/**
 * Article Crawler für Hammer Heimtex (ID: 67475)
 */
class Crawler_Company_Hammer_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        // Article from the current Brochure
        $searchUrl = 'https://www.hammer-heimtex.de/c/ham_aktuellebeilage?q=%3Arelevance&page=';
        $params = '?utm_source=offerista&utm_medium=paid&utm_campaign=hammer-zuhause&utm_term=beilage&utm_content=B10';

        $sPage = new Marktjagd_Service_Input_Page();
        $cArticle = new Marktjagd_Collection_Api_Article();
        foreach ($this->getDomDocs($searchUrl, $sPage) as $domDoc) {
            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle
                ->setTitle($sPage->getDomElsFromDomEl($domDoc, 'o-product-lister-grid-item__name')[0]->textContent)
                ->setUrl($domDoc->getAttribute('data-product-url') . $params)
                ->setText($sPage->getDomElsFromDomEl($domDoc, 'o-product-lister-grid-item__summary copy--small')[0]->textContent)
                ->setPrice('1');

            $cArticle->addElement($eArticle);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticle);
        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * @param string $baseUrl
     * @param Marktjagd_Service_Input_Page $sPage
     * @return array
     * @throws Exception
     */
    private function getDomDocs($baseUrl, Marktjagd_Service_Input_Page $sPage)
    {
        $domDocs = [];
        for ($i = 0; $i <= 23; $i++) {
            $domDoc = $sPage->getDomElsFromUrl($baseUrl . $i, "product-lister-grid-item", 'data-t-name');
            $domDocs = array_merge($domDocs, $domDoc);
        }
        return $domDocs;
    }
}