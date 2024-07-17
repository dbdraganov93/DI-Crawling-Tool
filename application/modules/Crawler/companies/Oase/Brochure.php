<?php

/**
 * Prospekt Crawler für Getränke Oase (ID: 69544)
 */
class Crawler_Company_Oase_Brochure extends Crawler_Generic_Company
{
    private const TITLE = 'Getränke Oase: Wochenangebote!';
    private const DATE_FORMAT = 'd.m.Y';
    private const WEEK = 'next';

    private Marktjagd_Service_Output_Pdf $pdf;

    public function __construct()
    {
        parent::__construct();

        $this->pdf = new Marktjagd_Service_Output_Pdf();
    }

    public function crawl($companyId)
    {

        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $timeService = new Marktjagd_Service_Text_Times();

        $weekKW = $timeService->getWeekNr(self::WEEK);

        $localPath = $ftp->connect($companyId, true);

        $brochureList = [];
        foreach ($ftp->listFiles() as $listFile) {
            if (preg_match('#KW' . $weekKW . '[^\.]*\.csv$#', $listFile)) {
                $referenceFile = $ftp->downloadFtpToDir($listFile, $localPath);
                continue;
            }
            if (preg_match('#(?<brochureName>KW' . $weekKW . '([^\.\/]+))\.pdf$#', $listFile, $match)) {
                $brochureList[strtolower($match['brochureName'])] = $ftp->downloadFtpToDir($listFile, $localPath);
            }
        }
        $ftp->close();

        if (empty($referenceFile)) {
            throw new Exception(
                'No reference excel file was found on our ftp server.'
            );
        }

        $brochuresData = $spreadsheetService->readFile($referenceFile, true, $this->detectCSVFileDelimiter($referenceFile))->getElement(0)->getData();

        $referenceData = [];
        foreach ($brochuresData as $brochureData) {
            if (count($brochureData) != 3) {
                throw new Exception('Company ID: ' . $companyId . ': invalid data format. Probably delimiter change?');
            }

            if (empty($brochureData['Dateiname'])) {
                continue;
            }

            if (array_key_exists(strtolower($brochureData['Dateiname']), $referenceData)) {
                $referenceData[strtolower($brochureData['Dateiname'])] = array_merge(
                    $referenceData[strtolower($brochureData['Dateiname'])], [(string)$brochureData['Filialen']]
                );
                continue;
            }

            $referenceData[strtolower($brochureData['Dateiname'])] = [(string)$brochureData['Filialen']];
        }

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochureList as $brochureName => $brochurePath) {
            $brochureData = $this->getValidity($brochurePath);
            if (!count($brochuresData)) {
                continue;
            }

            $brochureData['url'] = $brochurePath;
            $brochureData['store'] = $this->getStoreNumbersFromBrochureNameAndCsv($brochureName, $referenceData);
            $brochureData['number'] = substr($brochureName, 0, 32);

            $brochure = $this->createBrochure($brochureData);
            $brochures->addElement($brochure);
        }

        return $this->getResponse($brochures, $companyId);
    }

    private function getValidity(string $filePath): array
    {
        $dateNormalizer = new Marktjagd_Service_DateNormalization_Date();

        $extractedText = $this->pdf->extractText($filePath);
        if (!preg_match(
            '#(\d{1,2})\.((\d{1,2})\.)?\s?[-|\w]+\s?(\d{1,2})\.(\d{1,2})\.#', $extractedText, $dateMatch
        )) {
            return [];
        }

        $timestamp = strtotime(self::WEEK . ' week');
        if (!strlen($dateMatch[2])) {
            $start = $dateMatch[1] . '.' . date('m.Y', $timestamp);
        } else {
            $start = $dateMatch[1] . '.' . $dateMatch[3] . '.' . date('Y', $timestamp);
        }

        return [
            'start' => $dateNormalizer->normalize($start),
            'end' => $dateNormalizer->normalize($dateMatch[4] . '.' . $dateMatch[5] . '.' . date('Y', $timestamp))
        ];
    }

    private function getStoreNumbersFromBrochureNameAndCsv(string $brochureName, array $referenceData): ?string
    {
        if (preg_match('#ibb-rheine#', $brochureName) && empty($referenceData[$brochureName])) {
            $brochureName = str_replace('ibb-rheine', 'ibbenbueren-rheine', $brochureName);
        }

        if (preg_match('#do_brackel-nette-scharnhorst#', $brochureName) && empty($referenceData[$brochureName])) {
            $brochureName = str_replace('do_brackel-nette-scharnhorst', 'do-brackel-nette-scharnhorst', $brochureName);
        }

        if (empty($referenceData[$brochureName])) {
            $this->_logger->warn(
                'Add brochure ' . $brochureName . ', but no Stores were found for this PDF.'
            );
        } else {
            $this->_logger->info(
                'Add brochure ' . $brochureName . ' to the stores ' .
                implode(', ', $referenceData[$brochureName])
            );
        }

        return implode(', ', $referenceData[$brochureName]);
    }

    public static function detectCSVFileDelimiter($csvFile)
    {
        $delimiters = array(',' => 0, ';' => 0, "\t" => 0, '|' => 0,);
        $firstLine = '';
        $handle = fopen($csvFile, 'r');
        if ($handle) {
            $firstLine = fgets($handle);
            fclose($handle);
        }
        if ($firstLine) {
            foreach ($delimiters as $delimiter => &$count) {
                $count = count(str_getcsv($firstLine, $delimiter));
            }
            return array_search(max($delimiters), $delimiters);
        } else {
            return key($delimiters);
        }
    }

    private function createBrochure(array $data): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();
        return $brochure->setStoreNumber($data['store'])
            ->setUrl($data['url'])
            ->setVariety('leaflet')
            ->setStart($data['start'])
            ->setVisibleStart(date(self::DATE_FORMAT, strtotime($brochure->getStart() . ' - 1 day')))
            ->setEnd($data['end'])
            ->setTitle(self::TITLE)
            ->setBrochureNumber($data['number']);
    }
}
