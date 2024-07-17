<?php

/**
 * Allgemeiner FTP-Crawler für PDFs
 *
 * Class Crawler_Generic_Ftp_Brochure
 */
class Crawler_Generic_Ftp_Brochure extends Crawler_Generic_Company
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
        $this->_response = $crawlerFtp->crawl($companyId, 'brochures');

        return $this->_response;
    }
}