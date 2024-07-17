<?php

/*
 * Store Crawler für Möbel Flamme (ID: 73622)
 */

class Crawler_Company_Flamme_Store extends Crawler_Generic_Company
{
    private $_baseUrl = 'https://www.flamme.de/';

    public function crawl($companyId)
    {
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($this->getStoreData() as $partUrl => $storeData) {

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setWebsite($this->_baseUrl . $partUrl)
                ->setTitle($storeData[0])
                ->setStreetAndStreetNumber($storeData[1])
                ->setZipcodeAndCity($storeData[2])
                ->setPhoneNormalized($storeData[3])
                ->setFaxNormalized($storeData[4])
                ->setStoreHoursNormalized($storeData[5]);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getStoreData(): array
    {
        $sPage = new Marktjagd_Service_Input_Page();

        $pattern = '#data-controllerUrl="([^"]+)"#';
        $sPage->open($this->_baseUrl . 'standorte/');
        if (!preg_match($pattern, $sPage->getPage()->getResponseBody(), $storesUrl)) {
            return [];
        }

        $ret = [];
        foreach ($sPage->getUrlsFromUrl($this->_baseUrl . $storesUrl[1], '#orte-#') as $rawStoreUrl) {
            $sPage->open($rawStoreUrl);
            if (!preg_match($pattern, $sPage->getPage()->getResponseBody(), $storeUrl)) {
                continue;
            }
            $k = explode('/', $rawStoreUrl)[3];
            $ret[$k] = $this->getContents($sPage, $storeUrl[1]);

        }
        return $ret;
    }

    /**
     * @param Marktjagd_Service_Input_Page $sPage
     * @param string $storeUrl
     * @return array
     * @throws Exception
     */
    private function getContents(Marktjagd_Service_Input_Page $sPage, string $storeUrl): array
    {
        $ret = [];
        $startAddress = false;
        $startHours = false;
        $cnt = 1;
        $hrs = [];
        foreach ($sPage->getDomElsFromUrlByClass($this->_baseUrl . $storeUrl, 'dig-pub--text') as $domElsFromUrlByClass) {
            $content = $domElsFromUrlByClass->textContent;
            if (preg_match('#^\s*flamme#i', $content)) {
                $ret[] = $content;
                $startAddress = true;
                continue;
            }
            if (!$startAddress) {
                continue;
            }
            if (in_array($cnt, [1, 2])) {
                $ret[] = trim($content);
                $cnt++;
                continue;
            }
            if (preg_match('#tel|fax#i', $content)) {
                $ret[] = trim($content);
                continue;
            }
            if (preg_match('#ffnungszeiten#', $content) || !$startHours) {
                $startHours = true;
                continue;
            }
            if (preg_match('#[mo|\d+]#i', $content)) {
                $hrs[] = $content;
                continue;
            }
            $ret[] = trim(implode(', ', $hrs));
            break;
        }
        return $ret;
    }
}
