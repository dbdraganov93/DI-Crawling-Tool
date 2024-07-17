<?php

/**
 * FTP-Crawler für Artikel auf dem FTP von UIM
 *
 * Class Crawler_Generic_Ftp_Uim_Article
 */
class Crawler_Generic_Ftp_Uim_Article extends Crawler_Generic_Company
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
        $this->_response = $crawlerFtp->crawl($companyId, 'articles', 'uim');

        return $this->_response;
    }
}