<?php

/**
 * Storecrawler für Clinique (ID: 71940)
 */
class Crawler_Company_Clinique_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $sMjFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $cStores = new Marktjagd_Collection_Api_Store();

        $sMjFtp->connect($companyId);

        $localFile = $sMjFtp->downloadFtpToCompanyDir('adressen_m_oz.xlsx', $companyId);
        $aData = $sExcel->readFile($localFile,true)->getElement(0)->getData();

        foreach ($aData as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setTitle($singleStore['title']);
            $eStore->setStreetAndStreetNumber($singleStore['street']);
            $eStore->setCity($singleStore['city']);
            $eStore->setZipcode($singleStore['zipcode']);

            $aWeekdays = array(
                'Mo' => 'Monday',
                'Di' => 'Tuesday',
                'Mi' => 'Wednesday',
                'Do' => 'Thursday',
                'Fr' => 'Friday',
                'Sa' => 'Saturday',
                'So' => 'Sunday');

            $sOpening = '';
            foreach ($aWeekdays as $key => $weekday) {
                if (array_key_exists($weekday, $singleStore)
                    && $singleStore[$weekday] != 'closed'
                    && $singleStore[$weekday] != ''
                ) {
                    if (strlen($sOpening) > 0) {
                        $sOpening .= ', ';
                    }

                    $sOpening .= $key . ' ' . $singleStore[$weekday];
                }
            }

            $eStore->setStoreHoursNormalized($sOpening);
            $cStores->addElement($eStore);
        }
                
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}