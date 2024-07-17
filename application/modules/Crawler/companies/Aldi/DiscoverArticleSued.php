<?php

/**
 * Article crawler for Aldi Süd (ID: 29)
 */

class Crawler_Company_Aldi_DiscoverArticleSued extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sPage = new Marktjagd_Service_Input_Page();


        # look for an articles file and header psd on the FTP server
        $localPath = $sFtp->connect($companyId, TRUE);
        $sFtp->changedir('Discover');

        foreach ($sFtp->listFiles() as $singleFtpFile) {
            if (preg_match("#.xlsx$#i", $singleFtpFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleFtpFile, $localPath);
            }
        }
        $sFtp->close();

        if(!($localArticleFile)) {
            $this->_logger->warn('no articles or header pdf could be found');
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::FAILED);
            return $this->_response;
        }


        $aData = $sPss->readFile($localArticleFile, TRUE)->getElement(0)->getData();

        # build the URL for each article
        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleArticle) {

            if(empty($singleArticle['Site']) || !preg_match('#http#', $singleArticle['Site']))
                continue;

            # crawl the article data
            $sPage->open($singleArticle['Site']);
            $page = $sPage->getPage()->getResponseBody();
            $xpath = $this->createXPathFromUrl($page);

            $articleJson = json_decode($xpath->query('//script[@type="application/ld+json"]')->item(1)->textContent);


            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setTitle($singleArticle['titel'])
                ->setText($articleJson->description)
                ->setUrl($singleArticle['Site'])
                ->setImage($articleJson->image)
                ->setPrice($articleJson->offers->price)
                ->setStart($singleArticle['WT'] . ' 00:00:01')
                ->setEnd($singleArticle['WT'] . ' 23:59:59')
                ->setVisibleStart($eArticle->getStart());

            $cArticles->addElement($eArticle, true, 'complex', false);
        }


        return $this->getResponse($cArticles, $companyId);
    }

    private function createXPathFromUrl(string $url): DOMXPath
    {
        // ignore warnings
        $old_libxml_error = libxml_use_internal_errors(true);
        $domDoc = new DOMDocument();
        $domDoc->loadHTML($url);
        libxml_use_internal_errors($old_libxml_error);

        $xpath = new DOMXPath($domDoc);
        return $xpath;
    }
}
