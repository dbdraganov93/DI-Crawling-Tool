<?php

/*
 * Store Crawler für QUALIPET CH (ID: 72174)
 */

class Crawler_Company_QualipetCh_Store extends Crawler_Generic_Company {

    private const STORES_BASE_URL = 'https://www.qualipet.ch/standorte/';
    private const REGEX_STORES_LIST = '#var\s*markerData\s*=\s*(.+?);#';
    private const REGEX_STORE_PAGE_URL = '#<a[^>]*href="([^"]+?)"#';
    private const REGEX_STORE_ADDRESS_MAIN = '#>\s*([^<]+?)\s*<br[^>]*>\s*([^<]+?\s*<br[^>]*>\s*)*(\d{4}\s+[^<]+?)\s*<#';
    private const REGEX_STORE_ADDRESS_CHANGE = '#^(\d+)\s+(.+)#';
    private const REGEX_STORE_OPEN_HOURS = '#ffnungszeiten(.+?)</div>\s*</div>\s*</div#';
    private const REGEX_STORE_OPEN_HOURS_NORMALIZE = '#([A-Z][a-z])\s+(.+)#';
    private const REGEX_STORE_PHONE = '#<a[^>]*href="tel:[^>]*>\s*([^<]+?)\s*<#';
    private const REGEX_STORE_TEXT = '#<p[^>]*>Wir\s*verkaufen\s*([^<]+?)\.\s*<#';

    private Marktjagd_Service_Input_Page $sPage;

    public function crawl($companyId) {
        $this->sPage = new Marktjagd_Service_Input_Page();

        $jStores = $this->getStoreList($companyId);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $jStore) {
            $storeData = $this->getStoreData($jStore->infoWindowContent);

            if (empty($storeData)) {
                continue;
            }

            $eStore = $this->generateStore($storeData);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

    private function getStoreList(int $companyId): array
    {
        $this->sPage->open(self::STORES_BASE_URL);
        $page = $this->sPage->getPage()->getResponseBody();

        if (!preg_match(self::REGEX_STORES_LIST, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        return json_decode($storeListMatch[1]);
    }

    private function getStoreUrl(string $storeInfo): string
    {
        if (preg_match(self::REGEX_STORE_PAGE_URL, $storeInfo, $storeDetailUrlMatch)) {
            return $storeDetailUrlMatch[1];
        }

        return '';
    }

    private function getStoreAddress(string $storeInfo): array
    {
        if (!preg_match(self::REGEX_STORE_ADDRESS_MAIN, $storeInfo, $addressMatch)) {
            return [];
        }

        $strStreet = $addressMatch[1];

        if (strlen($addressMatch[2])) {
            if (
                (
                    preg_match('#\s+\d+#', $addressMatch[2])
                    ||
                    preg_match('#\d+\s+#', $addressMatch[2])
                )
                &&
                !preg_match('#\d+#', $addressMatch[1])
            ) {
                $strStreet = $addressMatch[2];
            }
        }

        if (preg_match(self::REGEX_STORE_ADDRESS_CHANGE, $strStreet, $addressChangeMatch)) {
            $strStreet = $addressChangeMatch[2] . ' ' . $addressChangeMatch[1];
        }

        return [
            'street' => $strStreet,
            'city_and_zip' => $addressMatch[3]
        ];
    }

    private function getOpenHours(string $page): string
    {
        $sTimes = new Marktjagd_Service_Text_Times();
        if (preg_match(self::REGEX_STORE_OPEN_HOURS, $page, $storeHoursMatch)) {
            return $sTimes->generateMjOpenings($storeHoursMatch[1]);
        }

        return '';
    }

    private function getNormalizedOpenHours(string $openHours): string
    {
        $aStoreHours = preg_split('#\s*,\s*#', $openHours);
        $aNewStoreHours = array();
        foreach ($aStoreHours as $singleDay) {
            if (preg_match(self::REGEX_STORE_OPEN_HOURS_NORMALIZE, $singleDay, $dateMatch)) {
                $aNewStoreHours[$dateMatch[1]] = $dateMatch[2];
            }
        }

        $strTime = '';
        foreach ($aNewStoreHours as $day => $time) {
            if (strlen($strTime)) {
                $strTime .= ',';
            }
            $strTime .= $day . ' ' . $time;
        }

        return $strTime;
    }

    private function getPhone(string $page): string
    {
        if (preg_match_all(self::REGEX_STORE_PHONE, $page, $phoneMatch)) {
            // take the second match because the first is the phone number in the header
            // note: this could change in the future
            return $phoneMatch[1][1] ?? '';
        }

        return '';
    }

    private function getText(string $page): string
    {
        if (preg_match_all(self::REGEX_STORE_TEXT, $page, $textMatches)) {
            return 'Wir verkaufen<br/>' . implode('<br/>', $textMatches[1]);
        }

        return '';
    }

    private function getStoreData($storeInfo): array
    {
        $storeUrl = $this->getStoreUrl($storeInfo);
        if (empty($storeUrl)) {
            return [];
        }

        $this->sPage->open($storeUrl);
        $page = $this->sPage->getPage()->getResponseBody();

        $storeAddress = $this->getStoreAddress($storeInfo);
        if (empty($storeAddress)) {
            return [];
        }

        $storeHours = $this->getOpenHours($page);
        $normalizedStoreHours = $this->getNormalizedOpenHours($storeHours);

        $storePhone = $this->getPhone($page);

        $storeText = $this->getText($page);

        return [
            'url' => $storeUrl,
            'street' => $storeAddress['street'],
            'city_and_zip' => $storeAddress['city_and_zip'],
            'hours' => $storeHours,
            'normalized_hours' => $normalizedStoreHours,
            'phone' => $storePhone,
            'text' => $storeText,

        ];
    }

    private function generateStore(array $storeData): Marktjagd_Entity_Api_Store
    {
        $eStore = new Marktjagd_Entity_Api_Store();

        $eStore->setWebsite($storeData['url'])
            ->setAddress($storeData['street'], $storeData['city_and_zip'], 'CH')
            ->setStoreHours($storeData['hours'])
            ->setStoreHoursNormalized($storeData['normalized_hours'])
            ->setPhoneNormalized($storeData['phone'])
            ->setText($storeData['text']);

        return $eStore;
    }
}
