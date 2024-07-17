<?php

/**
 * New Brochure Crawler for L.Stroetmann (ID: 71734)
 */

class Crawler_Company_LStroetmann_Brochure extends Crawler_Generic_Company
{
    const WEEK = 'next';
    const DATA_FORMAT = 'd.m.Y';
    const REGEX_WEEKEND_PAGE = '#(wochenend|von\s*donnerstag\s*bis\s*samstag)#';

    private Marktjagd_Service_Output_Pdf $pdfService;
    private string $localPath;

    public function __construct()
    {
        parent::__construct();

        $this->pdfService = new Marktjagd_Service_Output_Pdf();
    }

    public function crawl($companyId)
    {
        $brochures = new Marktjagd_Collection_Api_Brochure();
        $spreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();

        $downloadedFiles = $this->downloadFiles($companyId);
        $allStores = $spreadsheet->readFile($downloadedFiles['stores'], TRUE)->getElement(0)->getData();

        foreach ($downloadedFiles['brochures'] as $fileName => $brochurePath) {
            if (!count($this->getBrochureStores($allStores, $fileName))) {
                continue;
            }
            $brochureData = [
                'start' => strtotime(self::WEEK . ' week monday'),
                'end' => strtotime(self::WEEK . ' week saturday'),
                'visibleStart' => strtotime("-1 days", strtotime(self::WEEK . ' week monday')),
                'url' => $brochurePath,
                'stores' => implode(',', $this->getBrochureStores($allStores, $fileName)),
                'brochureNumber' => substr(preg_replace('#\s*.pdf#', '', $fileName), 0, 25)
            ];
            $lastPageBrochureData = NULL;
            if (!preg_match('#_4S\.pdf$#', $fileName)) {
                $lastPageBrochureData = $this->getLastPageBrochureData($brochureData, $fileName);
            }

            if ($lastPageBrochureData) {
                $specialBrochurePatch = $this->duplicatePdf($lastPageBrochureData['url']);
            }
            $normalBrochurePatch = $this->duplicatePdf($brochureData['url']);

            $brochures->addElement($this->createBrochure($brochureData));
            $brochures->addElement($this->createDuplicateBrochure($normalBrochurePatch, $brochureData));

            // add the last page as separate brochure
            if ($lastPageBrochureData) {
                $brochures->addElement($this->createBrochure($lastPageBrochureData));
                $brochures->addElement($this->createDuplicateBrochure($specialBrochurePatch, $lastPageBrochureData));
            }
        }

        return $this->getResponse($brochures);
    }

    private function downloadFiles(int $companyId): array
    {
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $files = [];
        $weekNr = date('W', strtotime(self::WEEK . ' week'));

        $this->localPath = $ftp->connect($companyId, TRUE);
        foreach ($ftp->listFiles() as $file) {
            if (preg_match('#KW' . $weekNr . '([^\.\/]+)\.pdf$#', $file)) {
                $files['brochures'][$file] = $ftp->downloadFtpToDir($file, $this->localPath);
            }
            if (preg_match('#Offerista[^.]*\.xlsx$#', $file)) {
                $files['stores'] = $ftp->downloadFtpToDir($file, $this->localPath);
            }
        }
        $ftp->close();

        return $files;
    }

    private function getBrochureStores(array $allStores, string $file): array
    {
        $storesArray = array_filter($allStores, function ($item) use ($file) {
            return $item['Versionen'] == explode('_', $file)[1];
        });

        $stores = [];
        foreach ($storesArray as $store) {
            $stores[] = $store['ZKDNR'];;
        }

        return $stores;
    }

    private function getLastPageBrochureData(array $brochureData, string $brochureFileName): array
    {
        $fileName = substr($brochureFileName, 0, -4);
        $this->pdfService->splitPdf($this->localPath . $brochureFileName);
        $pdfInfos = json_decode($this->pdfService->extractText($this->localPath . $brochureFileName));

        $lastPage = 0;
        foreach ($pdfInfos as $page => $info) {
            if (preg_match(self::REGEX_WEEKEND_PAGE, strtolower($info->text))) {
                $lastPage = $page + 1;
                break;
            }
        }

        if (!$lastPage) {
            return [];
        }

        $pages[] = $this->localPath . $fileName . '_separated_' . $lastPage . '.pdf';

        $brochureData['url'] = $this->pdfService->merge($pages, $this->localPath);
        $brochureData['brochureNumber'] = $brochureData['brochureNumber'] . '_WE';
        $brochureData['start'] = strtotime(self::WEEK . ' week thursday');
        $brochureData['end'] = strtotime(self::WEEK . ' week saturday');
        $brochureData['visibleStart'] = strtotime("-1 days", $brochureData['start']);

        return $brochureData;
    }

    private function createBrochure(array $data): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();
        $brochure->setTitle('L. Stroetmann: Wochenangebote')
            ->setUrl($data['url'])
            ->setStart(date(self::DATA_FORMAT, $data['start']))
            ->setEnd(date(self::DATA_FORMAT, $data['end']))
            ->setVisibleStart(date(self::DATA_FORMAT, $data['visibleStart']))
            ->setStoreNumber($data['stores'])
            ->setBrochureNumber($data['brochureNumber']);

        return $brochure;
    }

    private function duplicatePdf(string $brochurePath): string
    {
        $filePathCopy = preg_replace('#.pdf#', '_DLC.pdf', $brochurePath);
        copy($brochurePath, $filePathCopy);
        return $filePathCopy;
    }

    private function createDuplicateBrochure(string $brochurePath, array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $duplicateBrochureData = $brochureData;
        $duplicateBrochureData['url'] = $brochurePath;
        $duplicateBrochureData['brochureNumber'] = 'DLC_' . $brochureData['brochureNumber'];
        return $this->createBrochure($duplicateBrochureData);
    }
}
