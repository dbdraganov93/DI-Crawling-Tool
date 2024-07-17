<?php

/**
 * Storecrawler für Marionnaud (CH) (ID: 72262)
 */
class Crawler_Company_MarionnaudCh_Store extends Crawler_Generic_Company
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
        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();
        $baseUrl = 'http://www.marionnaud.ch';
        $detailUrl = $baseUrl . '/de/magasins';

        $aWeekDay = array(
            0 => 'Mo',
            1 => 'Di',
            2 => 'Mi',
            3 => 'Do',
            4 => 'Fr',
            5 => 'Sa',
            6 => 'So'
        );

        $sPage->open($detailUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="info\-store"[^>]*>(.*?)<\/div>#s';

        if (!preg_match_all($pattern, $page, $matchStores)) {
            throw new Exception($companyId . ': could not match any stores');
        }

        foreach ($matchStores[1] as $sStores) {
            $pattern = '#\s*<ul[^>]*>\s*<li[^>]*>\s*([^<]+)\s*<\/li>\s*<li[^>]*>\s*([^<]+?)\s*<\/li>\s*<li[^>]*>\s*([^<]+)\s*<\/li>\s*'
                . '<li[^>]*>\s*([^<]+)\s*<\/li>\s*<li[^>]*>\s*([^<]+)\s*<\/li>#';

            if (!preg_match($pattern, $sStores, $matchAddress)) {
                $this->_logger->err('could not match address for store ' . $sStores);
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreet($matchAddress[2])
                ->setStreetNumber($matchAddress[3])
                ->setZipcode($matchAddress[5])
                ->setCity($matchAddress[4]);

            if (preg_match('#^[a-zäöü]#i', $matchAddress[3])) {
                $eStore->setStreetAndStreetNumber($matchAddress[3], 'CH');
            }

            $pattern = '#<li[^>]*>\s*Telefonnummer\s*<\/li>\s*<li[^>]*>\s*([^<]+)\s*<\/li>#';
            if (preg_match($pattern, $page, $matchPhone)) {
                $eStore->setPhoneNormalized($matchPhone[1]);
            }

            $pattern = '#<li[^>]*class="weekDay"[^>]*>\s*[A-Z]+\:\s*([^<]+)\s*<\/li>#';
            if (preg_match_all($pattern, $sStores, $matchOpening)) {
                $sTimes = '';
                foreach ($matchOpening[1] as $keyWeekday => $time) {
                    if ($time == '-') {
                        continue;
                    }

                    if (strlen($sTimes)) {
                        $sTimes .= ', ';
                    }

                    $sTimes .= $aWeekDay[$keyWeekday] . ' ' . $time;
                }

                $eStore->setStoreHoursNormalized($sTimes);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}

