<?php

/*
 * Store Crawler für Idee+Spiel (ID: 22235)
 */

class Crawler_Company_IdeeUndSpiel_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $localDistFile = '';
        foreach ($sFtp->listFiles('./VB') as $singleFile) {
            if (preg_match('#\.xlsx?#', $singleFile)) {
                $localDistFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $aData = $sExcel->readFile($localDistFile, TRUE)->getElement(0)->getData();
        $aCampaign = array();
        foreach ($aData as $singleColumn) {
            foreach ($singleColumn as $singleFieldKey => $singleFieldValue) {
                if (preg_match('#lkz#i', $singleFieldKey)) {
                    if (!preg_match('#^d#i', $singleFieldValue)) {
                        break;
                    }
                }
            }
            foreach ($singleColumn as $singleFieldKey => $singleFieldValue) {
                if (preg_match('#plz#i', $singleFieldKey)) {
                    $aCampaign[] = $singleFieldValue;
                }
            }
        }

        $baseUrl = 'https://www.ideeundspiel.com/';
        $cStores = new Marktjagd_Collection_Api_Store();
        for ($lng = 5; $lng <= 15; $lng += 0.75) {
            for ($lat = 47; $lat <= 56; $lat += 0.75) {
                $sUrl = $baseUrl . "frontend-ui/tenants/ius/retailer/find?lat=$lat&lon=$lng&dist=50&offset=0&limit=100";
                if (!$sPage->open($sUrl)) {
                    $this->_logger->log($companyId . ': unable to open page', Zend_Log::CRIT);
                }
                $page = $sPage->getPage()->getResponseBody();
                $aStores = json_decode($page);
                if (!count($aStores)) {
                    continue;
                }
                foreach ($aStores as $singleStore) {
                    if (!preg_match('#([^<]+)<#', $singleStore->address, $street) ||
                        !preg_match('#>(.+)#', $singleStore->address, $city)) {
                        continue;
                    }
                    $eStore = new Marktjagd_Entity_Api_Store();
                    $eStore->setStoreNumber(substr(str_replace('-', '', $singleStore->storeId), -32, 32))
                        ->setTitle($singleStore->name)
                        ->setZipcodeAndCity($city[1])
                        ->setStreetAndStreetNumber($street[1])
                        ->setLogo($singleStore->logo)
                        ->setWebsite($singleStore->storeUrl)
                        ->setImage($singleStore->background);

                    if (in_array($eStore->getZipcode(), $aCampaign)) {
                        $eStore->setDistribution('Kampagne');
                    }

                    $cStores->addElement($eStore);
                }
                $this->_logger->info("$lat - $lng | Stores: " . count($cStores->getElements()));
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
