<?php
/**
 * Store Crawler für Auchan FR (ID: 72321)
 */

class Crawler_Company_AuchanFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://api.woosmap.com/';
        $searchUrl = $baseUrl . 'stores/?key=auchan-woos&page=';
        $sPage = new Marktjagd_Service_Input_Page();

        $aHeader = array(
            'referer: https://www.auchan.fr/magasins/votremagasin',
            'authority: api.woosmap.com'
        );

        $aDays = array(
            '1' => 'Mo',
            '2' => 'Di',
            '3' => 'Mi',
            '4' => 'Do',
            '5' => 'Fr',
            '6' => 'Sa',
            '7' => 'So'
        );

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 1; $i < 100; $i++) {
            $ch = curl_init($searchUrl . $i);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
            $result = curl_exec($ch);
            curl_close($ch);

            $jStores = json_decode($result);
            if (preg_match('#error#', $jStores->status)) {
                break;
            }

            foreach ($jStores->features as $singleJStore) {
                $strTime = '';
                foreach ($singleJStore->properties->weekly_opening as $timeKey => $singleDay) {
                    if (array_key_exists($timeKey, $aDays)) {

                        foreach ($singleDay->hours as $singleTime) {
                            if (strlen($strTime)) {
                                $strTime .= ',';
                            }
                            $strTime .= $aDays[$timeKey] . ' ' . $singleTime->start . '-' . $singleTime->end;
                        }
                    }
                }
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setLatitude($singleJStore->geometry->coordinates[1])
                    ->setLongitude($singleJStore->geometry->coordinates[0])
                    ->setStoreNumber($singleJStore->properties->store_id)
                    ->setStoreHoursNormalized($strTime)
                    ->setWebsite(preg_replace('#[^:]\/\/#', '/',$singleJStore->properties->contact->website))
                    ->setPhoneNormalized($singleJStore->properties->contact->phone)
                    ->setCity(ucwords(strtolower($singleJStore->properties->address->city)))
                    ->setStreetAndStreetNumber($singleJStore->properties->address->lines[0])
                    ->setZipcode($singleJStore->properties->address->zipcode);

                if (!preg_match('#^http#', $eStore->getWebsite())) {
                    $eStore->setWebsite('https://www.auchan.fr/magasins/' . $eStore->getWebsite());
                }

                if (strlen($eStore->getWebsite())) {
                    $sPage->open($eStore->getWebsite());
                    $page = $sPage->getPage()->getResponseBody();

                    $pattern = '#<div[^>]*class="store-rayons[^>]*>(.+?)</div#s';
                    if (preg_match($pattern, $page, $sectionListMatch)) {
                        $pattern = '#<img[^>]*alt="([^"]+?)"[^>]*>#';
                        if (preg_match_all($pattern, $sectionListMatch[1], $sectionMatches)) {
                            $eStore->setSection(implode(', ', $sectionMatches[1]));
                        }
                    }
                }

                $cStores->addElement($eStore, TRUE);
            }

        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
