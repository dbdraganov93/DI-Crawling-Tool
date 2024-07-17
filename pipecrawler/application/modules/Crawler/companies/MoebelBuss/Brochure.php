<?php
/**
 * Brochure crawler for Möbel Buss (ID: 81413)
 */

class Crawler_Company_MoebelBuss_Brochure extends Crawler_Generic_Company
{
    private const BROCHURE_DATE_FORMAT = 'd.m.Y';
    private const EMAIL_DATE_FORMAT = 'd.m.y';
    private const EMAIL_LABEL_NAME = 'MoebelBuss';
    protected string $companyId;
    protected Marktjagd_Service_Transfer_Email $emailService;
    protected Marktjagd_Service_Input_PhpExcel $excelService;
    protected Marktjagd_Service_Output_Pdf $pdfService;

    public function __construct()
    {
        parent::__construct();

        $this->emailService = new Marktjagd_Service_Transfer_Email();
        $this->excelService = new Marktjagd_Service_Input_PhpExcel();
        $this->pdfService = new Marktjagd_Service_Output_Pdf();
    }

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $this->companyId = $companyId;

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#PLZ#', $singleFile)) {
                $zipcodeFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            } elseif (preg_match('#^(\d{2})(\d{2})(\d{2})([^.]+)\.pdf$#', $singleFile)) {
                $pdf = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }
        $sFtp->close();

        $aZipcodes = $this->getZipcode($zipcodeFile);
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $emails = $this->emailService->generateEmailCollection($companyId, self::EMAIL_LABEL_NAME);
        foreach ($emails->getElements() as $eEmail) {
            $brochureData = $this->getValidity($eEmail);
            $clickoutFile = $this->getClickoutFile($eEmail);

            $pdfName = explode('_', $pdf);
            $brochureData['number'] = 'Buss-' . $pdfName[1];
            $brochureData['zipcode'] = $aZipcodes;
            $brochureData['pdf'] = $this->addClickoutToPdf($clickoutFile, $pdf);

            $eBrochure = $this->createBrochure($brochureData);
            if ($cBrochures->addElement($eBrochure)) {
                $this->emailService->archiveMail($eEmail);
            }
        }

        return $this->getResponse($cBrochures);
    }

    /**
     * @throws Exception
     */
    private function getValidity(Marktjagd_Entity_Email $email): array
    {
        $pattern = '#([0-9]{2}\.[0-9]{2}\.[0-9]{2,4})#';
        if (!preg_match_all($pattern, $email->getText(), $validityMatches)) {
            throw new Exception('Unable to get brochure validity');
        }
        $validityMatch = reset($validityMatches);
        return [
            'startDate' => $validityMatch[0],
            //date_create_from_format(self::EMAIL_DATE_FORMAT, $validityMatch[1])->format(self::BROCHURE_DATE_FORMAT),
            'endDate' => $validityMatch[1],
            //date_create_from_format(self::EMAIL_DATE_FORMAT, $validityMatch[2])->format(self::BROCHURE_DATE_FORMAT),
        ];
    }

    private function getClickoutFile(Marktjagd_Entity_Email $email): string
    {
        $clickoutFile = '';
        foreach ($email->getLocalAttachmentPath() as $name => $path) {
            if(strpos(strtolower($name), '.csv')!== false) {
                $clickoutFile = $path;
            }

        }
        return $clickoutFile;
    }

    private function addClickoutToPdf(string $clickoutFile, string $pdf): string
    {
        $aClickoutData = $this->excelService->readFile($clickoutFile, TRUE, ';')->getElement(0)->getData();
        $pdfInfos = $this->pdfService->getAnnotationInfos($pdf);

        foreach ($aClickoutData as $singleClickoutData) {
            $url = '';

            foreach ($singleClickoutData as $singleKey => $singleValue) {
                if (preg_match('#url#i', $singleKey) && strlen($singleValue) && preg_match('#^(https)#', $singleValue)) {
                    $url = $singleValue;
                }
            }

            if (empty($url)) {
                $url = end($singleClickoutData);
                if (empty($url)) {
                    $this->_logger->err($this->companyId . ': unable to get url for brochure: ' . $pdf);
                    continue;
                }
            }

            $pageNumber = (int)$singleClickoutData['page'] - 1;
            $pageInfo = $pdfInfos[$pageNumber];
            if (empty($pageInfo)) {
                continue;
            }

            $aCoordsToLink[] = [
                'page' => $pageNumber,
                'height' => $pageInfo->height,
                'width' => $pageInfo->width,
                'startX' => $singleClickoutData['left'] * $pageInfo->width,
                'endX' => ($singleClickoutData['left'] + $singleClickoutData['width']) * $pageInfo->width,
                'startY' => $pageInfo->height - ($singleClickoutData['top'] * $pageInfo->height),
                'endY' => $pageInfo->height - (($singleClickoutData['top'] + $singleClickoutData['height']) * $pageInfo->height),
                'link' => $url
            ];
        }

        $coordFileName = $this->localPath . 'coordinates_' . $this->companyId . '_' . '.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aCoordsToLink));
        fclose($fh);

        return $this->pdfService->setAnnotations($pdf, $coordFileName);
    }

    private function getZipcode(string $zipcodeFile): array
    {
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $aData = $sPss->readFile($zipcodeFile);

        $aZipcodes = [];
        foreach ($aData->getElements() as $singleSheet) {
            foreach ($singleSheet->getData() as $singleRow) {
                if (is_int($singleRow[1]) && strlen((string)$singleRow[1]) == 5 && !in_array($singleRow[1], $aZipcodes)) {
                    $aZipcodes[] = $singleRow[1];
                }
            }
        }

        return $aZipcodes;
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setTitle('Möbelangebote')
            ->setBrochureNumber($brochureData['number'])
            ->setUrl($brochureData['pdf'])
            ->setStart($brochureData['startDate'])
            ->setEnd($brochureData['endDate'])
            ->setVisibleStart($eBrochure->getStart())
            ->setVariety('leaflet')
            ->setZipCode(implode(',', $brochureData['zipcode']));

        return $eBrochure;
    }
}
