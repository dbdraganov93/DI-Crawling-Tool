<?php

class Crawler_Company_Nwz_FtpCheck extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $sFtp->connect($companyId);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.pdf#', $singleFile)) {
                throw new Exception($companyId . ': new file for nwz found: ' . $singleFile);
            }
        }

        $this->_response->setLoggingCode(4)
        ->setIsImport(FALSE);

        return $this->_response;
    }
}