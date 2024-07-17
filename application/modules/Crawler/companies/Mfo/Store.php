<?php

/**
 * Store Crawler für MFO Matratzen (ID: 72108)
 */
class Crawler_Company_Mfo_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page(true);

        $url = 'https://www.mfo-matratzen.de/service/filialfinder';
        $sPage->open($url);
        $page = print_r($sPage, true);

        preg_match_all('#JSON\.parse\(\'(.*)\'\)#i', $page, $rawjson);

        $json = json_decode($rawjson[1][0]);

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($json->studio as $singleStore){
            $eStore = new Marktjagd_Entity_Api_Store();

            $hours = $this->storeHours($singleStore);

            $eStore->setZipcode($singleStore->zip)
                ->setCity($singleStore->city)
                ->setStreetAndStreetNumber($singleStore->street)
                ->setTitle($singleStore->caption)
                ->setLatitude($singleStore->latitude)
                ->setLatitude($singleStore->longitude)
                ->setPhone($singleStore->tel)
                ->setEmail($singleStore->mail)
                ->setWebsite($singleStore->url)
                ->setStoreHoursNormalized($hours);

            $cStores->addElement($eStore);
        }


        return $this->getResponse($cStores, $companyId);
    }
    private function storeHours ($zeitArray) {
        $days = ['mo', 'di', 'mi', 'do','fr','sa'];
        $slots = [1, 2];
        $hours = '';
        foreach ($days as $day) {
            $tagesZeiten = '';
            foreach ($slots as $slot) {
                $begin = 'open_' . $day . '_from_' . $slot;
                $end = 'open_' . $day . '_to_' . $slot;
                $tagesZeiten = $tagesZeiten . $day .  ': ' . $zeitArray->$begin . ' - ' . $zeitArray->$end . ', ';
            }
            $hours = $hours . ', ' .$tagesZeiten;
        }
        return $hours;
    }
}
