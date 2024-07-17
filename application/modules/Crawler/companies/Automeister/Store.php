<?php
/**
 * Storecrawler für Automeister (ID: 28988)
 */
class Crawler_Company_Automeister_Store extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $url = 'ftp://marktjagd:jaegerundsammler@ftp.netrapid.de/automeister-standorte.csv';
                
        $cStores = new Marktjagd_Collection_Api_Store();
        $mjAddress = new Marktjagd_Service_Text_Address();        
        $mjTimes = new Marktjagd_Service_Text_Times();
        
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $downloadFolder = $sHttp->generateLocalDownloadFolder($companyId);

        $sDownload = new Marktjagd_Service_Transfer_Download();
        $filePath = $sDownload->downloadByUrl($url, $downloadFolder);
        
        $sExcel = new Marktjagd_Service_Input_PhpExcel();        
        $aStores = $sExcel->readFile($filePath, true, ';');
        $aStores = $aStores->getElements();
        
        foreach($aStores[0]->getData() as $singleElement){        
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleElement['store_number'])
                    ->setTitle($singleElement['subtitle'])
                    ->setSubtitle('Automeister')
                    ->setStreet($mjAddress->extractAddressPart('street', $singleElement['street']))
                    ->setStreetNumber($mjAddress->extractAddressPart('street_number', $singleElement['street']))
                    ->setZipcode($singleElement['zipcode'])
                    ->setCity($singleElement['city'])
                    ->setPhone($mjAddress->normalizePhoneNumber($singleElement['phone']))
                    ->setFax($mjAddress->normalizePhoneNumber($singleElement['fax']))
                    ->setEmail($singleElement['email'])
                    ->setStoreHours($mjTimes->generateMjOpenings($singleElement['OEFFNUNGSZEITEN']));
                        
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}