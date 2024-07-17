<?php

/*
 * Store Crawler für Kaufland (ID: 67394)
 */

class Crawler_Company_Kaufland_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $apiStores = $sApi->findStoresByCompany($companyId);
        $aStores = $this->getStoresFromFile($companyId);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStores as $singleStore) {
            if (!preg_match('#ACTIVE#i', $singleStore['status'])) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber(preg_replace('#DE#', '', $singleStore['identifier']))
                ->setStreetAndStreetNumber($singleStore['streetAndNumber'])
                ->setZipcode($singleStore['zip'])
                ->setCity($singleStore['city'])
                ->setLatitude($singleStore['lat'])
                ->setLongitude($singleStore['lng'])
                ->setSubtitle(str_replace('"', "'",$singleStore['addressExtra']))
                ->setPhone(preg_replace(array('#\+49#', '#\s+#'), array('0', ''), $singleStore['phone']))
                ->setFaxNormalized($singleStore['fax'])
                ->setWebsite($singleStore['website'])
                ->setStoreHoursNormalized(preg_replace(array('#=#', '#;#'), array(' ', ','), $singleStore['openingHours']))
                ->setLogo($singleStore['logo'])
                ->setSection($singleStore['services'])
                ->setDistribution($this->getDistByStoreId($apiStores, $singleStore['identifier']));
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * @param $companyId
     * @return array
     * @throws Exception
     */
    private function getStoresFromFile($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $sFtp->connect($companyId);
        $filePattern = '#stores\.xlsx$#';
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match($filePattern, $singleFile)) {
                $localFile = $sFtp->downloadFtpToCompanyDir($singleFile, $companyId);
                return $sExcel->readFile($localFile, TRUE)->getElement(0)->getData();
            }
        }
        throw new Exception("no file matches the pattern: $filePattern");
    }

    /**
     * @param $stores
     * @param $id
     * @return string
     */
    private function getDistByStoreId($stores, $id)
    {
        foreach ($stores->getElements() as $eStore) {
            if ($eStore->getStoreNumber() == $id)
                return $eStore->getDistribution();
        }
        return '';
    }
}
