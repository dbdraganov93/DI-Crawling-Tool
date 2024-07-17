<?php

/* 
 * Store Crawler für Getränke Quelle (ID: 69548)
 */

class Crawler_Company_GetraenkeQuelle_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $searchUrl = 'https://www.getraenke-quelle.net/index.php?option=com_storelocator&view=map&format=raw&searchall=1&Itemid=107&catid=-1&tagid=-1&featstate=0';

        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();

        foreach ($this->getStoresInfo($searchUrl) as $storeInfo) {

            $address = explode(',', $storeInfo->address);

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setTitle($storeInfo->name)
                ->setStreetAndStreetNumber($address[0])
                ->setZipcodeAndCity($address[1])
                ->setCity($sAddress->getGerCityName($eStore->getZipcode()) ?: $eStore->getCity())
                ->setLatitude($storeInfo->lat)
                ->setLongitude($storeInfo->lng)
                ->setPhoneNormalized(!is_object($storeInfo->phone) ? $storeInfo->phone : '')
                ->setStoreHoursNormalized($storeInfo->custom4);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }

    /**
     * @param string $searchUrl
     * @return array
     * @throws Exception
     */
    private function getStoresInfo(string $searchUrl): array
    {
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $xml = simplexml_load_string($page, null, LIBXML_NOCDATA);
        return json_decode(json_encode($xml))->marker;
    }
}