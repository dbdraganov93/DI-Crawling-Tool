<?php

/**
 * Store Crawler für Obi DE, CH and AT (ID: 74, 72146, 73321)
 */
class Crawler_Company_Obi_Store extends Crawler_Generic_Company
{
    private const BASE_URL = 'https://www.obi.de';
    private const FTP_FOLDER = '74';
    private const SALES_REGION_MAP = [
        'A' => 'GROUP A',
        'B' => 'GROUP B',
    ];
    private const COMPANY_TO_LOCALIZATION_MAP = [
        74 => 'de',
        72146 => 'ch',
        73321 => 'at',
    ];


    public function crawl($companyId)
    {
        $storeDistributions = $this->getStoreDistributions($companyId);

        $storesData = $this->requestStoresData($companyId);

        $stores = new Marktjagd_Collection_Api_Store();
        foreach ($storesData as $items) {

            foreach ($items as $storeObject) {
                $storeData = $this->getStoreData($storeObject, $storeDistributions);
                $store = $this->createStore($storeData);
                $stores->addElement($store);
            }
        }

        return $this->getResponse($stores, $companyId);
    }

    private function getStoreDistributions(int $companyId): array
    {
        $spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();

        $assignmentFiles = $this->downloadAssignmentFiles($companyId);

        $storeDistributions = [];
        foreach ($assignmentFiles as $regionKey => $assignmentFile) {
            $storeList = $spreadsheetService->readFile($assignmentFile, TRUE)->getElement(0)->getData();

            foreach($storeList as $store) {
                $storeDistributions[$store['Zipcode']] = self::SALES_REGION_MAP[$regionKey];
            }
        }

        return $storeDistributions;
    }

    private function downloadAssignmentFiles(int $companyId): array
    {
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localPath = $ftp->connect(self::FTP_FOLDER, TRUE);

        $assignmentFiles = [];
        foreach ($ftp->listFiles() as $singleFile) {
            if (preg_match('#^GroupA_zipcodes\.xlsx?$#', $singleFile)) {
                $assignmentFiles['A'] = $ftp->downloadFtpToDir($singleFile, $localPath);
            }
            elseif (preg_match('#^GroupB_zipcodes\.xlsx?$#', $singleFile)) {
                $assignmentFiles['B'] = $ftp->downloadFtpToDir($singleFile, $localPath);
            }
        }
        $ftp->close();

        if (!isset($assignmentFiles['A']) || !isset($assignmentFiles['B'])) {
            throw new Exception('Could not download assignment files for company ' . $companyId);
        }

        return $assignmentFiles;
    }

    private function requestStoresData(int $companyId): object
    {
        $requestUrl = self::BASE_URL . '/storeLocatorRest/v1/stores/getAllByCountry/de/'
            . self::COMPANY_TO_LOCALIZATION_MAP[$companyId]
            . '?fields=name,address,phone,services,hours,storeNumber,path,email';

        $ch = curl_init($requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response);
    }

    private function getStoreData(object $storeObject, array $storeDistributions): array
    {
        return [
            'address' => $storeObject->address->street,
            'zip' => $storeObject->address->zip,
            'city' => preg_replace('#\s*,\s*.+#', '', $storeObject->name),
            'lat' => $storeObject->address->lat,
            'lon' => $storeObject->address->lon,
            'phone' => str_replace('+ 41 ', '0', $storeObject->phone),
            'fax' => $storeObject->fax,
            'services' => ucwords(implode(', ', array_unique($storeObject->services))),
            'hours' => preg_replace('#\n#', ',', $storeObject->hours),
            'storeNumber' => $storeObject->storeNumber,
            'distribution' => $storeDistributions[$storeObject->zip],
            'website' => self::BASE_URL . $storeObject->path,
        ];
    }

    private function createStore(array $storeData): Marktjagd_Entity_Api_Store
    {
        $eStore = new Marktjagd_Entity_Api_Store();

        return $eStore->setStreetAndStreetNumber($storeData['address'])
            ->setZipcode($storeData['zip'])
            ->setCity($storeData['city'])
            ->setLatitude($storeData['lat'])
            ->setLongitude($storeData['lon'])
            ->setPhone($storeData['phone'])
            ->setFax($storeData['fax'])
            ->setService($storeData['services'])
            ->setStoreHoursNormalized($storeData['hours'], 'text', TRUE, 'ita')
            ->setStoreNumber($storeData['storeNumber'])
            ->setDistribution($storeData['distribution'])
            ->setWebsite($storeData['website']);
    }

}
