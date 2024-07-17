<?php
/**
 * Store Crawler für Maisons Du Monde FR (ID: 73602)
 */

class Crawler_Company_MaisonsDuMondeFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPhpSpreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.xlsx#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        $sFtp->close();

        $aData = $sPhpSpreadsheet->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleColumn) {
            if (!preg_match('#FR#', $singleColumn['Pays'])) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleColumn['id_magasin_charactere'])
                ->setStreetAndStreetNumber($singleColumn['Adresse'], 'fr')
                ->setCity(strtolower($singleColumn['Ville']))
                ->setZipcode($singleColumn['CP'])
                ->setPhoneNormalized(preg_replace('#\(33\)#', '0', $singleColumn['Phone']));

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}