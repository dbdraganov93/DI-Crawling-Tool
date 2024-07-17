<?php
/**
 * Store Crawler für Livique CH (ID: 72149)
 */

class Crawler_Company_LiviqueCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.coop.ch/';
        $searchUrl = $baseUrl . 'de/services/standorte-und-oeffnungszeiten.getvstlist.json?lat=46.8064773&lng=7.161971900000026&' .
            'start=1&end=100&filterFormat=livique&filterAttribute=&filterOpen=false&gasIndex=0';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->vstList as $singleJStore) {
            if (!preg_match('#livique#', $singleJStore->formatId)) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->betriebsNummerId->id)
                ->setLatitude($singleJStore->latitude)
                ->setLongitude($singleJStore->longitude)
                ->setZipcode($singleJStore->plz)
                ->setStreet($singleJStore->strasse)
                ->setStreetNumber($singleJStore->hausnummer)
                ->setCity($singleJStore->ort)
                ->setWebsite('https://www.coop.ch/de/services/standorte-und-oeffnungszeiten/detail.html/'
                    . $singleJStore->betriebsNummerId->id . '/' . $singleJStore->prettyUrlName . '.html');

            $sPage->open('https://www.coop.ch/content/vstinfov2/de/detail.getvstopeninghours.json?id='
                . $singleJStore->betriebsNummerId->id . '&language=gb');
            $jStoreHours = $sPage->getPage()->getResponseAsJson();

            $aTime = array();
            if (count($jStoreHours->hours)) {
                foreach ($jStoreHours->hours as $singleJDay) {
                    if (array_key_exists($singleJDay->desc, $aTime)) {
                        break;
                    }

                    $aTime[$singleJDay->desc] = $singleJDay->desc . ' ' . $singleJDay->time;
                }
            }

            $eStore->setStoreHoursNormalized(implode(',', $aTime));

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}