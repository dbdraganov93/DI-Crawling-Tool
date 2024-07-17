<?php

/**
 * Store Crawler für ReifenHelm (ID: 69979)
 */
class Crawler_Company_ReifenHelm_Store extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.reifenhelm.de';
        $searchUrl = $baseUrl . '/standort/';

        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        if (!preg_match_all('#class\=\"geolocations\-list\-item\"(.*?)<\/p><\/div>#', $page, $storeRaws)) {
            throw new Exception($companyId . ': unable to get store list. Pattern doesnt match');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeRaws[1] as $storeRaw) {
            $eStore = new Marktjagd_Entity_Api_Store;
            $eStore->setStoreNumber($this->getResultWithPattern($storeRaw, '#\s+id\=\"([^"]*)#'))
                ->setLatitude($this->getResultWithPattern($storeRaw, '#data-latitude\=\"([^"]*)#'))
                ->setLongitude($this->getResultWithPattern($storeRaw, '#data-longitude\=\"([^"]*)#'))
                ->setTitle($this->getResultWithPattern($storeRaw, '#class\=\"location\-title\"[^>]*>([^<]*)#'))
                ->setStreetAndStreetNumber($this->getResultWithPattern($storeRaw, '#class\=\"location\-address\"[^>]*>([^,|(]*)#'))
                ->setZipcodeAndCity($this->getResultWithPattern($storeRaw, '#class\=\"location\-address\"[^>]*>[^,]*,([^<]*)#'))
                ->setPhoneNormalized($this->getResultWithPattern($storeRaw, '#class\=\"location\-phone\"[^>]*>([^<]*)#'))
                ->setEmail($this->getResultWithPattern($storeRaw, '#mailto:([^"]*)#'))
                ->setSection($this->getResultsWithPattern($storeRaw, '#category-icon\s*([^"]*)#'))
                ->setStoreHoursNormalized($this->getResultWithPattern($storeRaw, '#class\=\"location\-body\"[^>]*>\s*<p[^>]*>(.*?)<\/p>#'));

            if (!$cStores->addElement($eStore)) {
                $this->_logger->err("$companyId: store could not be added" . $eStore->getStoreNumber());
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * @param $storeRaw
     * @param $pattern
     * @param string $separator
     * @return string
     */
    private function getResultWithPattern($storeRaw, $pattern, $separator = ' ')
    {
        preg_match($pattern, $storeRaw, $results);
        unset($results[0]);
        return trim($this->getCleanString(implode($separator, $results)));
    }

    /**
     * @param $str
     * @param string $delimiter
     * @return string
     */
    private function getCleanString($str, $delimiter = ' ')
    {
        $strAsArray = explode($delimiter, $str);
        $prev = '';
        foreach ($strAsArray as $key => $word) {
            if ($prev == $word) {
                unset($strAsArray[$key]);
            }
            $prev = $word;
        }
        return implode($delimiter, $strAsArray);
    }

    /**
     * @param $storeRaw
     * @param $pattern
     * @param string $separator
     * @return string
     */
    private function getResultsWithPattern($storeRaw, $pattern, $separator = ', ')
    {
        preg_match_all($pattern, $storeRaw, $result);
        return implode($separator, $result[1]);
    }

}
