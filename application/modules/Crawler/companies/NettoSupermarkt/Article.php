<?php
/**
 * Prospektcrawler für Netto Supermarkt (ID: 73)
 *
 * Class Crawler_Company_NettoSupermarkt_Article
 */
class Crawler_Company_NettoSupermarkt_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $cArticle = new Marktjagd_Collection_Api_Article();
        $feedUrl = 'http://api.dsg.dk/WCF-services/Products.svc/GetProducts';

        $sPage = new Marktjagd_Service_Input_Page(true);
        $sPage->open($feedUrl);
        $jsonResult = $sPage->getPage()->getResponseAsJson();
        
        foreach ($jsonResult as $article) {
            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setTitle(preg_replace('#\*#', '', $article->Name))
                     ->setPrice($article->Price)
                     ->setText(preg_replace('#\s*<[^br][^>]*>\s*#', '', str_replace("\xe2\x80\x8b", '', $article->LongDescription)))
                     ->setStart($article->StartDate)
                     ->setEnd($article->EndDate)
                     ->setVisibleStart($article->VisibleFrom)
                     ->setVisibleEnd($article->VisibleTo)
                     ->setImage($article->ImageUrl);

            $cArticle->addElement($eArticle);
        }

        // Collection generieren
        $sFile = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sFile->generateCsvByCollection($cArticle);

        return $this->_response->generateResponseByFileName($fileName);
    }
}