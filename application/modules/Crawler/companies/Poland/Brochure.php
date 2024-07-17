<?php

class Crawler_Company_Poland_Brochure extends Crawler_Generic_Company
{
    private $localPath;
    private const DEFAULT_LANG = 'pl_pl';
    private const DEFAULT_DATE_FORMAT = 'd-m-Y';
    private const DEFAULT_DATETIME_FORMAT = 'd-m-Y H:i:s';

    public const POLAND_COMPANY_MAP = [
        '81282' => 1040,
        '81296' => 1180,
        '81204' => 1261,
        '81294' => 1024,
        '81197' => 1125,
        '81332' => 1058,
        '81194' => 1007,
        '81286' => 1193,
        '81307' => 1249,
        '81280' => 1207,
        '81231' => 1320,
        '81293' => 1124,
        '81265' => 976,
        '81209' => 1314,
        '81301' => 1166,
        '81303' => 1229,
        '81219' => 1103,
        '81193' => 5,
        '81239' => 2,
        '81202' => 1274,
        '81195' => 1067,
        '81247' => 1306,
        '81261' => 487,
        '81227' => 833,
        '81223' => 1277,
        '81226' => 1315,
        '90229' => 677,
        '90230' => 652,
        '81308' => 1296,
        '81323' => 1170,
    ];

    public function crawl($companyId)
    {
        $shopfullyCompanyId = self::POLAND_COMPANY_MAP[$companyId] ?: null;

        if ($shopfullyCompanyId === null) {
            return false;
        }

        $brochures = new Marktjagd_Collection_Api_Brochure();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $this->localPath = $sHttp->generateLocalDownloadFolder($companyId);

        // Is mendatory to pass the language to the API
        $api = new Shopfully_Service_BrochureApi(self::DEFAULT_LANG);

        // We need to pass the shopfully retailer_id to the API to get all flyer data
        $brochuresData = $api->getBrochures($shopfullyCompanyId, true, false);

        foreach ($brochuresData as $brochureData) {
            // Skip brochures with only one page
            if (1 === $brochureData->getNumberOfPages()) {
                continue;
            }

            $pdfPatch = $sHttp->getRemoteFile($brochureData->getPdfUrl(), $this->localPath);
            $brochure = $this->createBrochure($brochureData, $pdfPatch);
            $brochures->addElement($brochure);
        }


        return $this->getResponse($brochures, $companyId);
    }

    private function createBrochure(Shopfully_Entity_Brochure $brochureData, string $pdfPath): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();
        $brochure->setUrl($pdfPath)
            ->setBrochureNumber($brochureData->getId())
            ->setTitle($brochureData->getTitle())
            ->setStart($brochureData->getStartDate()->format(self::DEFAULT_DATE_FORMAT))
            ->setEnd($brochureData->getEndDate()->format(self::DEFAULT_DATE_FORMAT))
            ->setVisibleStart($brochureData->getPublishAt()->format(self::DEFAULT_DATETIME_FORMAT))
            ->setVariety('leaflet');

        $stores = $brochureData->getStores();
        if ($stores) {
            $brochure->setStoreNumber(implode(',', $stores));
        }

        return $brochure;
    }
}
