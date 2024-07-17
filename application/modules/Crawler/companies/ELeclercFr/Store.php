<?php
/**
 * Store Crawler für E.Leclerc FR (ID: 72314)
 */

class Crawler_Company_ELeclercFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.e-leclerc.com/';
        $searchUrl = $baseUrl . 'api/portail/public/magasin_rechercher_gps?latitudeGPS='
            . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '&longitudeGPS='
            . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.1, 'FR');
        foreach ($aUrls as &$singleUrl) {
            $singleUrl = preg_replace('#(\d)\.(\d)#', '$1$2', $singleUrl);
            $pattern = '#(-?\d+)#';
            if (preg_match_all($pattern, $singleUrl, $coordMatches)) {
                foreach ($coordMatches[1] as $singleCoord) {
                    $singleUrl = preg_replace('#' . $singleCoord . '#', str_pad($singleCoord, 8, '0', STR_PAD_RIGHT), $singleUrl);
                }
            }
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            if (!count($jStores->magasins)) {
                continue;
            }

            foreach ($jStores->magasins as $singleJStore) {
                $strTimes = '';
                foreach ($singleJStore->horairesSemaine as $singleDay) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }
                    $strTimes .= $singleDay->libelleJourSemaine . ' ' . preg_replace('#(\d+)h(\d+)#', '$1:$2', $singleDay->horaireFormate);
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setZipcode($singleJStore->coordonnee->codePostal)
                        ->setFaxNormalized($singleJStore->coordonnee->fax)
                        ->setLatitude($singleJStore->coordonnee->latitudeGPS)
                        ->setLongitude($singleJStore->coordonnee->longitudeGPS)
                        ->setPhoneNormalized($singleJStore->coordonnee->telephone)
                        ->setCity(ucwords(strtolower($singleJStore->coordonnee->ville)))
                        ->setStreetAndStreetNumber(ucwords(strtolower($singleJStore->coordonnee->voie)))
                        ->setStoreHoursNormalized($strTimes, 'text', TRUE, 'fra')
                        ->setStoreNumber($singleJStore->codePanonceau);

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $filename = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($filename);
    }
}
