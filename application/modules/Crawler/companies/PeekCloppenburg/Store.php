<?php

/**
 * Store Crawler für Peek & Cloppenburg (ID: 28923)
 */
class Crawler_Company_PeekCloppenburg_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.peek-und-cloppenburg.de/';
        $searchUrl = $baseUrl . 'haeuser/haeuser-uebersicht/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]*\/haeuser-uebersicht\/[A-z-]*\/)\?*"#';
        if (!preg_match_all($pattern, $page, $storeLinks)) {
            throw new Exception('unable to get stores: ' . $searchUrl);
        }

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.xls#', $singleFile)) {
                $localExtraOpeningFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }
        $sFtp->close();

        $aData = $sPss->readFile($localExtraOpeningFile, TRUE)->getElement(0)->getData();
        foreach ($aData as $singleRow) {
            $aInfos[$singleRow['PLZ']] =
                'Mo ' . $singleRow['Montag'] .
                ',Di ' . $singleRow['Dienstag'] .
                ',Mi ' . $singleRow['Mittwoch'] .
                ',Do ' . $singleRow['Donnerstag'] .
                ',Fr ' . $singleRow['Freitag'] .
                ',Sa ' . $singleRow['Samstag'];
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinks[1] as $storeLink) {
            if (preg_match('#outlet#', $storeLink)) {
                continue;
            }
            $rawData = $sPage->getDomElsFromUrlByClass($storeLink, 'pc-grid__item pc-grid__item--size_33');
            for ($i = 0; $i < count($rawData); $i++) {
                if (!preg_match('#ADRESS#', $rawData[$i]->textContent)) {
                    continue;
                }
                $info = $rawData[$i]->textContent;
            }

            if (!strlen($info)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeLink);
                continue;
            }

            $sPage->open($storeLink);
            $geoInfo = $sPage->getPage()->getResponseBody();

            $pattern = '#se(.*)(\d{5}\b.*)\s*Öf.*zeiten(.*)kontakt(.*)\s*Ku.*:#i';
            if (!preg_match($pattern, $info, $addressData)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeLink);
                continue;
            }

            $pattern = '#latitude.+?"(\d{1,2}\.\d*)[",\s]*[^,][A-z]*?.\s"(\d{1,2}\.\d*)[^,]#s';
            if (!preg_match($pattern, $geoInfo, $latLong)) {
                $this->_logger->err($companyId . ': unable to get store geo data: ' . $storeLink);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber($this->getStreet($addressData[1]))
                ->setZipcodeAndCity($this->cleanseCity($addressData[2]))
                ->setPhoneNormalized($addressData[4])
                ->setWebsite($storeLink)
                ->setLatitude($latLong[1])
                ->setLongitude($latLong[2]);

            if (array_key_exists($eStore->getZipcode(), $aInfos)) {
                $eStore->setStoreHoursNormalized($aInfos[$eStore->getZipcode()]);
            } else {
                $eStore->setStoreHoursNormalized($addressData[3])
                    ->setStoreHoursNotes('vorübergehend geschlossen.');
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function getStreet($str)
    {
        $pattern = '#([A-Z][a-z]+)(([A-Z][a-z]+).*[^"])#';
        if (!preg_match($pattern, $str, $streetCamelCaseFixed)) {
            $pattern = '#([A-zß:\s]+\d+)([A-zß:\s]+\d+)#';
            // falls eine Postanschrift hinterlegt ist, wird die angenommen dass die 1. Adresse die des Stores ist
            if (preg_match($pattern, $str, $postAnschrift)) {
                return $postAnschrift[1];
            }
            return $str;
        }
        return $streetCamelCaseFixed[2];
    }

    private function cleanseCity($zipCity)
    {
        // Stadtname ohne vorangegangens &nbsp rausziehen
        preg_match('#(\d{5})[^A-z]*([A-Z][a-zöäü]*)#', $zipCity, $clean);
        return ($clean[1] . " " . $clean[2]);
    }


}
