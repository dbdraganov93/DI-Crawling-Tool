<?php

/**
 * Store Crawler für Gerry Weber (ID: 28825)
 */
class Crawler_Company_GerryWeber_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://group.gerryweber.com/';
        $searchUrl = $baseUrl . 'media/storefinder/api.php?action=selectAllStores';

        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $page = curl_exec($ch);
        curl_close($ch);

        $sections = array(
            'gw' => 'Gerry Weber Collection',
            'ge' => 'Gerry Weber Edition',
            'ta' => 'Taifun',
            'sa' => 'Samoon'
        );

        $jStores = json_decode($page);
        $cStore = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->sl as $singleJStore) {
            if (!preg_match('#(house\s*of\s*gerry\s*weber|gerry\s*weber\s*outlet)#i', $singleJStore->n1)
                || !preg_match('#DE#', $singleJStore->cc)) {
                continue;
            }
            $strTime = '';
            if ($singleJStore->oh) {
                foreach ($singleJStore->oh as $singleDay) {
                    if (strlen($strTime)) {
                        $strTime .= ',';
                    }
                    $strTime .= $singleDay->wd . ' ' . $singleDay->mf . '-' . $singleDay->at;
                }
            }

            $strSection = '';
            foreach ($sections as $key => $name) {
                if ($singleJStore->$key) {
                    if (strlen($strSection)) {
                        $strSection .= ', ';
                    }
                    $strSection .= $name;
                }
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setCity($singleJStore->ci)
                ->setLatitude($singleJStore->la)
                ->setLongitude($singleJStore->lo)
                ->setStoreNumber($singleJStore->id)
                ->setStoreHoursNormalized($strTime)
                ->setPhoneNormalized($singleJStore->ph)
                ->setStreetAndStreetNumber($singleJStore->st)
                ->setZipcode($singleJStore->zp)
                ->setSection($strSection);

            if (preg_match('#outlet#i', $singleJStore->n1)) {
                $eStore->setTitle('Gerry Weber Outlet');
            }

            $cStore->addElement($eStore);

        }

        return $this->getResponse($cStore, $companyId);
    }

}
