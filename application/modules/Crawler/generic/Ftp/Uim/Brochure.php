<?php

/**
 * FTP-Crawler für Prospekte auf dem FTP von UIM
 *
 * Class Crawler_Generic_Ftp_Uim_Brochure
 */
class Crawler_Generic_Ftp_Uim_Brochure extends Crawler_Generic_Company
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
        $this->_response = $crawlerFtp->crawl($companyId, 'brochures', 'uim');

        return $this->_response;
    }
}