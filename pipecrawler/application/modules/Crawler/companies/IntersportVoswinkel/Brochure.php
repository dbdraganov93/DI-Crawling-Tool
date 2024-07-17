<?php

/**
 * Storecrawler für Intersport Voswinkel (ID: 71994)
 */
class Crawler_Company_IntersportVoswinkel_Brochure extends Crawler_Generic_Company
{
    private const COMPANY       = 8;
    private const STREET        = 5;
    private const CITY          = 7;
    private const PLZ           = 6;
    private const BROCHURE_V1   = 9;
    private const BROCHURE_V4   = 10;
    private const BROCHURE_V2   = 11;
    private const BROCHURE_V3   = 12;
    private const BROCHURE_HU   = 13;

    /**
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $startDate = '27.10.2021';
        $endDate   = '11.11.2021';
        $title     = 'Intersport Voswinkel: 10% Rabatt!';

        $cStores = $sApi->findStoresByCompany($companyId);

        $localFolder = $sFtp->connect($companyId, true);

        $brochures = [];
        foreach ($sFtp->listFiles() as $singleFile) {
            if(preg_match('#\.xlsx$#', $singleFile)) {
                $this->_logger->info('Downloading Excel file: ' . $singleFile);
                $assignmentExcelFile = $sFtp->downloadFtpToDir($singleFile, $localFolder);
                continue;
            }

            if(preg_match('#\.pdf$#', $singleFile)) {
                $this->_logger->info('Downloading PDF: ' . $singleFile);
                $brochures[$singleFile] = $sFtp->downloadFtpToDir($singleFile, $localFolder);
            }
        }

        $sFtp->close();

        if(empty($brochures) || !isset($assignmentExcelFile)) {
            throw new Exception('The crawler is missing some files on FTP and cant continue');
        }

        $aData = $sExcel->readFile($assignmentExcelFile)->getElement(0)->getData();

        $brochureAllocation = [
            'V1' => [],
            'V4' => [],
            'V2' => [],
            'V3' => [],
            'HU' => [],
        ];
        foreach ($aData as $singleLine) {
            if(empty($singleLine[self::COMPANY]) || empty($singleLine[self::PLZ])) {
                $this->_logger->info('Skipping line: ' . implode(',', $singleLine));
                continue;
            }

            if($singleLine[self::PLZ] == 'PLZ'){
                continue;
            }

            if(!empty($singleLine[self::BROCHURE_V1])) {
                array_push($brochureAllocation['V1'], $singleLine[self::PLZ]);
            }
            if(!empty($singleLine[self::BROCHURE_V4])) {
                array_push($brochureAllocation['V4'], $singleLine[self::PLZ]);
            }
            if(!empty($singleLine[self::BROCHURE_V2])) {
                array_push($brochureAllocation['V2'], $singleLine[self::PLZ]);
            }
            if(!empty($singleLine[self::BROCHURE_V3])) {
                array_push($brochureAllocation['V3'], $singleLine[self::PLZ]);
            }
            if(!empty($singleLine[self::BROCHURE_HU])) {
                array_push($brochureAllocation['HU'], $singleLine[self::PLZ]);
            }
        }

        foreach ($brochures as $brochureName => $brochureLocalPath) {
            if(preg_match('#_V1#', $brochureName, $match)) {
                $plzs = $brochureAllocation['V1'];
            } elseif (preg_match('#_V4#', $brochureName, $match)) {
                $plzs = $brochureAllocation['V4'];
            } elseif (preg_match('#_V2#', $brochureName, $match)) {
                $plzs = $brochureAllocation['V2'];
            } elseif (preg_match('#_V3#', $brochureName, $match)) {
                $plzs = $brochureAllocation['V3'];
            } elseif (preg_match('#_HU#', $brochureName, $match)) {
                $plzs = $brochureAllocation['HU'];
            }

            $storesIds = [];
            foreach ($cStores->getElements() as $store) {
                /** @var $store Marktjagd_Entity_Api_Store */
                if(in_array($store->getZipcode(), $plzs)) {
                    $storesIds[] = $store->getStoreNumber();
                }
            }

            if(empty($storesIds)) {
                throw new Exception('Something is wrong, no store was found in the ZIP list!');
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle($title)
                ->setBrochureNumber($match[0] . '_' . date('d.m.Y'))
                ->setStart($startDate)
                ->setEnd($endDate)
                ->setVisibleStart($eBrochure->getStart())
                ->setStoreNumber(implode(',', $storesIds))
                ->setVariety('leaflet')
                ->setUrl($brochureLocalPath);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}
