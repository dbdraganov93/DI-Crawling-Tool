<?php

/**
 * Store Crawler für Matratzen Concord (ID: 120)
 *
 */
class Crawler_Company_MatratzenConcord_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $mappingPatterns = [
            'number' => ['Geschäftscode', 'FilialNr'],
            'streetAndNr' => ['Adresszeile 1', 'STR'],
            'zipcode' => ['Postleitzahl', 'PLZ'],
            'city' => ['Ort', 'ORT'],
            'tel' => ['Primäre Telefonnummer'],
            'mo' => ['Öffnungszeiten (montags)'],
            'di' => ['Öffnungszeiten (dienstags)'],
            'mi' => ['Öffnungszeiten (mittwochs)'],
            'do' => ['Öffnungszeiten (donnerstags)'],
            'fr' => ['Öffnungszeiten (freitags)'],
            'sa' => ['Öffnungszeiten (samstags)'],
            'so' => ['Öffnungszeiten (sonntags)'],
        ];

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Filialliste#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        $aStores = $sExcel->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStores as $singleStore) {
            $data = $this->getMappedStoreData($mappingPatterns, $singleStore);
            $storeHours = "mo: $data[mo], di: $data[di], mi: $data[mi], do: $data[do], fr: $data[fr], sa: $data[sa], so: $data[so]";

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($data['number'])
                ->setStreetAndStreetNumber($data['streetAndNr'])
                ->setZipcode($data['zipcode'])
                ->setCity($data['city'])
                ->setPhoneNormalized($data['tel'])
                ->setStoreHoursNormalized($storeHours);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * @param $aMapping
     * @param $data
     * @return array
     */
    private function getMappedStoreData($aMapping, $data)
    {
        $mappedData = [];
        $dataKeys = array_keys($data);
        foreach ($aMapping as $key => $mapping) {
            $mappedData[$key] = $data[array_intersect($mapping, $dataKeys)[0]];
        }
        return $mappedData;
    }

}