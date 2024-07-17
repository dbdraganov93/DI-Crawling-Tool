<?php

/**
 * Artikelcrawler für Betten Rid (ID: 68758)
 */
class Crawler_Company_BettenRid_Article extends Crawler_Generic_Company
{

    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $sDownload = new Marktjagd_Service_Transfer_Download();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $downloadPath = $sHttp->generateLocalDownloadFolder($companyId);
        $downloadPathFile = $sDownload->downloadByUrl(
                'http://transport.productsup.io/0bc501721e5ee1199b73/2707/bettenrid_marktjagd.csv', $downloadPath);

        $sMjCsv = new Marktjagd_Service_Input_MarktjagdCsv();
        $cArticle = $sMjCsv->convertToCollection($downloadPathFile, 'articles');


        $cArticleNew = new Marktjagd_Collection_Api_Article();
        $usedArticleNumbers = array();

        foreach ($cArticle->getElements() as $eArticle)
        {
            /* @var $eArticle Marktjagd_Entity_Api_Article */
            if ($eArticle->getSuggestedRetailPrice() == '0')
            {
                $eArticle->setSuggestedRetailPrice('');
            }

            // Doppelte Artikel herausfiltern => nach dem Bindestrich dann nur Variante von Artikel
            $articleNumber = $eArticle->getArticleNumber();
            if (preg_match('#^([^\-]+)\-#', $articleNumber, $match))
            {
                $articleNumber = $match[1];
            }

            if (in_array($articleNumber, $usedArticleNumbers))
            {
                continue;
            }
            $eArticle->setArticleNumber($articleNumber);
            $eArticle->setStart('');

            $usedArticleNumbers[] = $articleNumber;

            if (preg_match('#nopic#', $eArticle->getImage()))
            {
                continue;
            }
            $cArticleNew->addElement($eArticle);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticleNew);
        $this->_response->generateResponseByFileName($fileName);
        return $this->_response;
    }

}
