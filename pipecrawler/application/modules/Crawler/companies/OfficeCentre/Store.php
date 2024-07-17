<?php

/**
 * Storecrawler für OfficeCentre (ID: 317)
 */
class Crawler_Company_OfficeCentre_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $cStores = new Marktjagd_Collection_Api_Store();
        $sFtp    = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss    = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Filial_Informationen_Stand200421\.xlsx#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $storesData = $sPss->readFile($localStoreFile, true)->getElement(0)->getData();

        foreach ($storesData as $key => $store) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber(iconv('UTF-8', 'WINDOWS-1252//TRANSLIT', $store['streetAndNumber']), 'FR')
                ->setZipcode($store['zip'])
                ->setCity(iconv('UTF-8', 'WINDOWS-1252//TRANSLIT', $store['city']))
                ->setPhoneNormalized($store['phone'])
                ->setFax($store['fax'])
                ->setWebsite($store['website'])
                ->setEmail($store['email'])
                ->setText(iconv('UTF-8', 'WINDOWS-1252//TRANSLIT', $store['descriptionShort']))
                ->setStoreHoursNormalized($this->generateOpeningHoursString($store['openingHours']))
                ->setStoreNumber($key)
            ;

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function generateOpeningHoursString(string $openingHours) : string
    {
        $explodedString = explode(';', $openingHours);

        $result = [];
        foreach ($explodedString as $dayOpeningHours) {
            if($dayOpeningHours == 'Su') {
                continue;
            }

            $result[] = str_replace('=', ' ', $dayOpeningHours);
        }

        return implode(', ', $result);
    }
}
