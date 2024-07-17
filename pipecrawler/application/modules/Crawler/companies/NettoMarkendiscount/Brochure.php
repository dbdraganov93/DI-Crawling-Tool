<?php

/**
 * Prospektcrawler für Netto Marken Discount (ID: 103)
 *
 * Class Crawler_Company_NettoMarkenDiscount_Brochure
 */
class Crawler_Company_NettoMarkendiscount_Brochure extends Crawler_Generic_Company
{
    private bool $isSpecialUpload;
    private string $week;

    public function __construct()
    {
        parent::__construct();

        $currentDay = date('N');
        $this->isSpecialUpload = (1 == $currentDay || 2 == $currentDay);
        $this->week = $this->isSpecialUpload ? 'this' : 'next';
    }

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.netto-online.de/';
        $searchUrl = $baseUrl . 'api/products/get_leaflets';
        $searchUrl2 = $baseUrl . 'ueber-netto/Online-Prospekte.chtm';
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sPage = new Marktjagd_Service_Input_Page();

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $oPage->setUseCookies(true);
        $sPage->setPage($oPage);

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $cStores = $sApi->findStoresByCompany($companyId);

        $year = $sTimes->getWeeksYear($this->week);
        $week = $sTimes->getWeekNr($this->week);

        $aParams = [
            'api_user' => 'Offerista',
            'api_token' => 'HXWCrNdZtUo00IscGVrg',
            'period' => $year . '-' . str_pad($week, 2, '0'),
        ];


        $storeZipcodes = [];
        if ($this->isSpecialUpload) {
            $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
            $localPath = $ftp->connect($companyId, TRUE);
            $localFile = '';
            foreach ($ftp->listFiles() as $file) {
                if ('Store zipcodes.xlsx' === $file) {
                    $localFile = $ftp->downloadFtpToDir($file, $localPath);
                    break;
                }
            }

            $spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();
            $storesData = $spreadsheetService->readFile($localFile, TRUE)->getElement(0)->getData();
            $storeZipcodes = array_map(function ($item) {
                return $item['PLZ'];
            }, $storesData);
        }

        $cBrochure = new Marktjagd_Collection_Api_Brochure();
        /* @var Marktjagd_Entity_Api_Distribution $eDistribution */
        foreach ($cStores->getElements() as $eStore) {
            if ($this->isSpecialUpload && !in_array($eStore->getZipcode(), $storeZipcodes)) {
                continue;
            }

            $aParams['store_id'] = $eStore->getStoreNumber();

            $sPage->open($searchUrl, $aParams);
            $json = $sPage->getPage()->getResponseAsJson();

            if (!$json->data) {
                $this->_logger->info('No brochures for store: ' . $eStore->getStoreNumber());
                continue;
            }

            $pdfFilePath = '';
            foreach ($json->data as $leaflet) {
                if (preg_match('#(Filialhandzettel|Getränkemarkt)#', $leaflet->type)) {
                    $pdfFilePath = $leaflet->pdf[0];
                    $week = $leaflet->KW;
                    $year = $leaflet->KWJahr;
                    $type = $leaflet->type;
                    $pdfName = preg_replace('#\.pdf#', '', $leaflet->pdf_name[0]);
                    break;
                }
            }

            if (!$pdfFilePath) {
                continue;
            }

            $startDate = $sTimes->getBeginOfWeek($year, $week);
            $endDate = $sTimes->getEndOfWeek($year, $week);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setUrl($pdfFilePath)
                ->setTitle(preg_match('#(Filialhandzettel)#', $type) ? 'Netto: Wochenangebote' : 'Netto: Getränkeangebote')
                ->setStart((string)date('d.m.Y', $startDate))
                ->setEnd((string)date('d.m.Y', $endDate))
                ->setVisibleStart(date('d.m.Y', strtotime('-2 days', $startDate)) . ' 19:00:00')
                ->setVisibleEnd(date('d.m.Y', $endDate) . ' 20:00:00')
                ->setStoreNumber($eStore->getStoreNumber())
                ->setBrochureNumber(str_pad($week, 2, '0') . '_' . $year . '_' . $pdfName)
                ->setVariety('leaflet');

            $cBrochure->addElement($eBrochure);
            $awsPath = $eBrochure->getUrl();

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setUrl($awsPath)
                ->setTitle(preg_match('#(Filialhandzettel)#', $type) ? 'Netto: Wochenangebote' : 'Netto: Getränkeangebote')
                ->setStart((string)date('d.m.Y', $startDate))
                ->setEnd((string)date('d.m.Y', strtotime('-1 days', $endDate)))
                ->setVisibleStart(date('d.m.Y', strtotime('-2 days', $startDate)))
                ->setVisibleEnd($eBrochure->getEnd() . ' 01:00:00')
                ->setStoreNumber($eStore->getStoreNumber())
                ->setBrochureNumber(str_pad($week, 2, '0') . '_' . $year . '_' . $pdfName . '_WA')
                ->setVariety('leaflet');

            $cBrochure->addElement($eBrochure);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setUrl($awsPath)
                ->setTitle('Netto: Wochenangebote')
                ->setStart((string)date('d.m.Y', $startDate))
                ->setEnd((string)date('d.m.Y', $endDate))
                ->setVisibleStart(date('d.m.Y', strtotime('-2 days', $startDate)))
                ->setVisibleEnd($eBrochure->getEnd() . ' 01:00:00')
                ->setStoreNumber($eStore->getStoreNumber())
                ->setBrochureNumber(str_pad($week, 2, '0') . '_' . $year . '_' . $pdfName . '_DLC')
                ->setVariety('leaflet');

            $cBrochure->addElement($eBrochure);
        }

        return $this->getResponse($cBrochure, $companyId);
    }

}
