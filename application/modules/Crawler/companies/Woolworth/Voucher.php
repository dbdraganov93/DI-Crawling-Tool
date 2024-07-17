<?php

/*
 * Voucher Crawler für Woolworth (ID: 79)
 */
class Crawler_Company_Woolworth_Voucher extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sFtp       = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sExcel     = new Marktjagd_Service_Input_PhpExcel();
        $sApi       = new Marktjagd_Service_Input_MarktjagdApi();

        $localPath = $sFtp->connect($companyId, true);
        $sFtp->changedir('voucher');

        $activeStores = $sApi->findAllStoresForCompany($companyId);

        $activeStoresZips = [];
        foreach ($activeStores as $activeStore) {
            $activeStoresZips[$activeStore['zipcode']] = $activeStore['number'];
        }

        foreach ($sFtp->listFiles() as $singleFile) {
            if(preg_match('#WW_30Euro-5Euro_Gutschein_A4.pdf#', $singleFile)){
                $voucherPDF = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }
            if(preg_match('#Filialliste.xlsx#', $singleFile)){
                $excelFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $excelData = $sExcel->readFile($excelFile, true)->getElement(0)->getData();

        $excelDataZips = [];
        foreach ($excelData as $data) {
            if($data['PLZ'] == null) {
                continue;
            }

            $excelDataZips[$data['PLZ']] = $data['PLZ'];
        }

        $storeNumberResults = [];
        foreach ($excelDataZips as $zips) {

            foreach ($activeStoresZips as $activeStoreZip => $activeStoreNumber) {
                if((string) $activeStoreZip == (string) $zips) {
                    $storeNumberResults[] = $activeStoreNumber;
                }
            }
        }

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setStoreNumber(implode(',', $storeNumberResults))
            ->setUrl($voucherPDF)
            ->setVariety('leaflet')
            ->setStart('08.12.2021')
            ->setVisibleStart($eBrochure->getStart())
            ->setEnd('19.12.2021')
            ->setTitle('Woolworth: Voucher')
            ->setBrochureNumber('12_21_voucher')
        ;

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }
}
