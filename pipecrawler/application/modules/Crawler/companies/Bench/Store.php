<?php

/**
 * Storecrawler für Bench (ID: 69918)
 */
class Crawler_Company_Bench_Store extends Crawler_Generic_Company
{
    protected $_baseUrl = 'http://www.bench.de/store-locator';

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();

        if (!$sPage->open($this->_baseUrl)) {
            throw new Exception('unable to get store-list-page of company with id ' . $companyId);
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#GERMANY</a>\s*<ul[^>]*>\s*(.+?)\s*<li[^>]*>\s*<a[^>]*class="country-link"#';
        if(!preg_match($pattern, $page, $sStoreList)) {
            $logger->log('unable to get store-list of company with id ' . $companyId, Zend_Log::ERR);
        }

        $pattern = '#SL.googleMap.openInfo\((.+?),\s*(.+?),\s*\'(.+?)(\s*t\:.+?)?\'\)#';
        if(!preg_match_all($pattern, $sStoreList[1], $sMatches)) {
            $logger->log('unable to get stores of company with id ' . $companyId, Zend_Log::ERR);
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        $mjAddress = new Marktjagd_Service_Text_Address();

        for ($i = 0; $i<count($sMatches[0]); $i++) {
            $eStore = new Marktjagd_Entity_Api_Store();
            if (strlen($sMatches[4][$i])) {
                $phone = $mjAddress->normalizePhoneNumber($sMatches[4][$i]);
                $eStore->setPhone($phone);
            }

            $sAddress = $sMatches[3][$i];
            $pattern = '#(^[A-ZÖÜÄ]*)\s*.+([0-9]{5})#';
            if(!preg_match($pattern, $sAddress, $match)) {
                $logger->log(
                    'unable to get zip/city for store of company with id ' . $companyId,
                    Zend_Log::ERR
                );

                continue;
            }
            $sAddress = preg_replace(array('#' . $match[1] . '\s+#i', '#\s*'
                    . $match[2] .'\s*#i', '#A\.M#i', '#\s*\(leipzig\)\s*#i'),
                    array('', '', '', ''), $sAddress);
            $eStore ->setCity(ucfirst(strtolower($match[1])))
                    ->setZipcode($match[2])
                    ->setLatitude($sMatches[1][$i])
                    ->setLongitude($sMatches[2][$i])
                    ->setStreet($sAddress);

            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        $crawlerResponse = new Crawler_Generic_Response();
        $crawlerResponse->generateResponseByFileName($fileName);

        return $crawlerResponse;
    }
}