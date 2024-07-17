<?php
/**
 * Store Crawler für Moebelix AT (ID: 73091)
 */

class Crawler_Company_MoebelixAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.moebelix.at/';
        $searchUrl = $baseUrl . 'filialen/AT';
        $sPage = new Marktjagd_Service_Input_Page();
        $utm = '?utm_source=wogibtswas.at&utm_medium=coop&utm_campaign=filialen';

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        // anderen Teil der website als Quelle einbinden, um auch die url der stores zu bekommen <section>
        $urlPage = $sPage->getDomElsFromUrlByClass($searchUrl, 'stores subentry');
        $extractedLinks = array();
        foreach ($urlPage as $storeLinks) {
            $links = $storeLinks->getElementsByTagName('a');
            foreach ($links as $link) {
                $extractedLinks[] = $link->getAttribute('href');
            }
        }

        $pattern = '#xxxl\.tracking\.rest\.addToAjaxCache\("\\\/rest\\\/desktop\\\/v2\\\/subsidiaries",\s*(.+?)\);#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list');
        }

        $jStores = json_decode($storeListMatch[1]);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->subsidiaries as $singleJStore) {
            if (!preg_match('#AT#', $singleJStore->address->country)) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->code)
                ->setStreetAndStreetNumber($singleJStore->address->street)
                ->setZipcode($singleJStore->address->postalcode)
                ->setCity($singleJStore->address->town)
                ->setPhoneNormalized($singleJStore->address->phone1)
                ->setFaxNormalized($singleJStore->address->fax)
                ->setEmail($singleJStore->address->email)
                ->setLongitude($singleJStore->address->longitude)
                ->setLatitude($singleJStore->address->latitude)
                ->setStoreHoursNormalized($singleJStore->openingHours);


            $m = preg_grep("#$singleJStore->code#", $extractedLinks);
            if (empty($m)) {
                continue;
            } else {
                foreach ($m as $key => $value) {
                    $eStore->setWebsite($value . $utm);
                }
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}