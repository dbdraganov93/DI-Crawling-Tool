<?php

/**
 * Allgemeiner FTP-Crawler für Artikel
 *
 * Class Crawler_Generic_Ftp_Article
 */
class Crawler_Generic_Ftp_Article extends Crawler_Generic_Company
{
    /**
     * Methode, die den Crawlingprozess initiert
     *
     * @param int $companyId
     * @return bool
     */
    function crawl($companyId)
    {
        $crawlerFtp = new Crawler_Generic_Ftp();
        $this->_response = $crawlerFtp->crawl($companyId, 'articles');

        return $this->_response;
    }
}