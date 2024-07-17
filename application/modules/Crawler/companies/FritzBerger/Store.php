<?php

/**
 * Storecrawler für Fritz Berger (ID: 29015)
 */
class Crawler_Company_FritzBerger_Store extends Crawler_Generic_Company {

    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $url = 'http://berger-data.de/service/marktjagd/filiale.csv';

        $sHttp = new Marktjagd_Service_Transfer_Http();
        $downloadFolder = $sHttp->generateLocalDownloadFolder($companyId);
        $sDownload = new Marktjagd_Service_Transfer_Download();
        $fileName = $sDownload->downloadByUrl($url, $downloadFolder);
        $intTimeNow = (int) date('n', strtotime('now'));
        $aSeasonNumbers = array(
            'Januar' => 1,
            'Februar' => 2,
            'März' => 3,
            'April' => 4,
            'Mai' => 5,
            'Juni' => 6,
            'Juli' => 7,
            'August' => 8,
            'September' => 9,
            'Oktober' => 10,
            'November' => 11,
            'Dezember' => 12
        );

        $mjCsv = new Marktjagd_Service_Input_MarktjagdCsv();
        /* @var $cStore Marktjagd_Collection_Api_Store */
        $cStore = $mjCsv->convertToCollection($fileName, 'stores');

        $sTimes = new Marktjagd_Service_Text_Times();
        $cNewStores = new Marktjagd_Collection_Api_Store();
        foreach ($cStore->getElements() as $eStore) {
            $strTime = '';
            /* @var $eStore Marktjagd_Entity_Api_Store */
            $aSeasons = preg_split('#\s*;\s*#', $eStore->getStoreHoursNotes());
            $pattern = '#([a-zäöü]{3,})\s*\-\s*([a-zäöü]{3,})\s*,?\s*(.+)#i';
            foreach ($aSeasons as $singleField) {
                if (preg_match($pattern, $singleField, $monthMatch)) {
                    $intEndMonth = $aSeasonNumbers[$monthMatch[2]];
                    if ($aSeasonNumbers[$monthMatch[2]] < $aSeasonNumbers[$monthMatch[1]]) {
                        $intEndMonth += 12;
                    }
                    if ($intTimeNow >= $aSeasonNumbers[$monthMatch[1]] && $intTimeNow <= $intEndMonth) {
                        $strTime = preg_replace('#([A-Z]{1}[a-z]{1})\s*([A-Z]{1}[a-z]{1})#', '$1-$2', $monthMatch[3]);
                        break;
                    }
                }
            }

            $eStore->setStoreHours($sTimes->generateMjOpenings($strTime))
                    ->setStoreHoursNotes(NULL);
            if (preg_match('#Dortmund#', $eStore->getCity()) || preg_match('#Duisburg#', $eStore->getCity())) {
                continue;
            }
            $cNewStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cNewStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
