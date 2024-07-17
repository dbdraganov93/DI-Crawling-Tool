<?php

/**
 * Allgemeiner FTP-Crawler für Standorte
 *
 * Class Crawler_Generic_Ftp_Store
 */
class Crawler_Generic_Ftp_Store extends Crawler_Generic_Company
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
        $this->_response = $crawlerFtp->crawl($companyId, 'stores');

        return $this->_response;
    }
}