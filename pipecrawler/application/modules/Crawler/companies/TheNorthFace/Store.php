<?php

/**
 * Storecrawler für The NorthFace (ID: 69942)
 */
class Crawler_Company_TheNorthFace_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://hosted.where2getit.com/';
        $searchUrl = $baseUrl . 'northfaceeu/ajax?lang=de_DE&xml_request=%3C'
                . 'request%3E%3Cappkey%3E3A992F50-193E-11E5-91BC-C90E919C4603%3C%2F'
                . 'appkey%3E%3Cformdata+id%3D%22getlist%22%3E%3Cobjectname%3E'
                . 'StoreLocator%3C%2Fobjectname%3E%3Climit%3E200%3C%2Flimit%3E'
                . '%3Cwhere%3E%3Ccountry%3E%3Ceq%3EDE%3C%2Feq%3E%3C%2Fcountry%3E'
                . '%3Cor%3E%3Cyouth%3E%3Ceq%3E%3C%2Feq%3E%3C%2Fyouth%3E%3Cmountain_athletics%3E'
                . '%3Ceq%3E%3C%2Feq%3E%3C%2Fmountain_athletics%3E%3Capparel%3E%3Ceq%3E%3C%2Feq%3E'
                . '%3C%2Fapparel%3E%3Cfootwear%3E%3Ceq%3E%3C%2Feq%3E%3C%2Ffootwear%3E'
                . '%3Cequipment%3E%3Ceq%3E%3C%2Feq%3E%3C%2Fequipment%3E%3Cnorthface%3E%3Ceq%3E'
                . '1%3C%2Feq%3E%3C%2Fnorthface%3E%3Cretailstore%3E%3Ceq%3E%3C%2Feq%3E%3C%2Fretailstore%3E'
                . '%3Coutletstore%3E%3Ceq%3E%3C%2Feq%3E%3C%2Foutletstore%3E%3Csummit%3E%3Ceq%3E%3C%2Feq%3E'
                . '%3C%2Fsummit%3E%3Cmt%3E%3Ceq%3E%3C%2Feq%3E%3C%2Fmt%3E%3C%2For%3E%3C%2Fwhere%3E%3C%2Fformdata%3E%3C%2Frequest%3E';
        $sTimes = new Marktjagd_Service_Text_Times();

        $aWeekDays = array(
            'f' => 'Fr',
            'm' => 'Mo',
            'sa' => 'Sa',
            'su' => 'So',
            't' => 'Di',
            'thu' => 'Do',
            'w' => 'Mi'
        );

        $curl = curl_init($searchUrl);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($curl);
        curl_close($curl);

        $xmlStores = new SimpleXMLElement($response);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($xmlStores->collection->poi as $singleXmlStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $strTimes = '';
            foreach ($aWeekDays as $engl => $deu) {
                $strHours = preg_replace('#\.#', ':', $singleXmlStore->{$engl});
                $aHours = preg_split('#to#', $strHours);
                foreach ($aHours as &$singleHours) {
                    if (!preg_match('#:(\d{1,2})#', $singleHours)) {
                        $singleHours = preg_replace('#(\d{1,2})(a|p)#', ' $1:00$2', $singleHours);
                    }
                }
                if (!strlen($aHours[0])) {
                    continue;
                }
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }
                $strTimes .= $deu . ' ' . $sTimes->convertAmPmTo24Hours($aHours[0]) . '-' . $sTimes->convertAmPmTo24Hours($aHours[1]);
            }

            $aAddress = preg_split('#\s*,\s*#', $singleXmlStore->address1);

            $eStore->setStreetAndStreetNumber((string) end($aAddress))
                    ->setCity(preg_replace(array('#Ü#', '#Ö#', '#Munich#'), array('ü', 'ö', 'München'), ucwords(strtolower((string) $singleXmlStore->city))))
                    ->setZipcode((string) $singleXmlStore->postalcode)
                    ->setLatitude((string) $singleXmlStore->latitude)
                    ->setLongitude((string) $singleXmlStore->longitude)
                    ->setPhoneNormalized((string) $singleXmlStore->phone)
                    ->setStoreHoursNormalized($strTimes)
                    ->setImage($singleXmlStore->store_image)
            ;

            if (count($aAddress) > 1) {
                $eStore->setSubtitle($aAddress[0]);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
