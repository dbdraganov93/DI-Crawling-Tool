<?php

/**
 * Service zum Verwenden der PhpExcel Funktionalität
 */
class Marktjagd_Service_Input_PhpExcel
{
    /**
     * Liest ein Excel-File ein und liefert eine Collection mit Worksheets zurück
     *
     * PHPExcel wird aufgrund der auf dem Server aktivierten mb_*-Funktionen
     * per Script mit deaktivierten mb_*-Funktionen aufgerufen
     *
     * @param string $fileName absoluter Dateipfad
     * @param bool $isMapHeadline wenn true, wird ein assoziatives Array mit den Keys aus der Headline erzeugt
     * @param string $delimiter Delimiter für CSV-Dateien
     * @return Marktjagd_Collection_PhpExcel_Worksheet
     */
    public function readFile($fileName, $isMapHeadline = false, $delimiter = null)
    {
        $cExcelSheet = new Marktjagd_Collection_PhpExcel_Worksheet();

        chdir(APPLICATION_PATH . '/../scripts/');
        $output = array();
        $returnVar = null;
        $command = 'php -d mbstring.func_overload=0'
            . ' phpexcel.php ' . escapeshellarg($fileName) . ' '
            . escapeshellarg((int)$isMapHeadline);

        if ($delimiter) {
            $command .= ' ' . escapeshellarg($delimiter);
        }

        exec($command, $output, $returnVar);

        $aExcelSheets = false;
        foreach ($output as $item) {
            if ($this->isJson($item)) {
                $aExcelSheets = json_decode($item, true);
                break;
            }
        }

        if (!$aExcelSheets || $returnVar > 0) {
            return $cExcelSheet;
        }

        foreach ($aExcelSheets as $key => $excelSheet) {
            $eExcelSheet = new Marktjagd_Entity_PhpExcel_Worksheet();
            $eExcelSheet->setId($key)
                ->setTitle($excelSheet['title'])
                ->setHighestRow($excelSheet['highestRow'])
                ->setHighestColumn($excelSheet['highestColumn'])
                ->setHighestColumnIndex($excelSheet['highestColumnIndex']);
            if (array_key_exists('data', $excelSheet)) {
                $eExcelSheet->setData($excelSheet['data']);
            }

            $cExcelSheet->addElement($eExcelSheet);
        }

        return $cExcelSheet;
    }

    /**
     * Json Validator
     * @param $json
     * @return bool
     */
    public function isJson($json)
    {
        json_decode($json);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * Liest eine CSV-Datei ein und schreibt alle Zeilen in eine XLS-Datei
     *
     * @param string $csvFileName Pfad zur CSV-Datei
     * @param string $xlsFileName Pfad zur gewünschten XLS-Ausgabe-Datei
     * @param string $delimiter Delimiter der CSV
     * @param string $csvEncoding Encoding der CSV-Datei
     *
     * @return bool|string
     */
    public function convertCsvToXls($csvFileName, $xlsFileName, $delimiter = ';', $csvEncoding = 'utf-8')
    {
        chdir(APPLICATION_PATH . '/../scripts/');
        $output = array();
        $returnVar = null;
        $command = 'php -d mbstring.func_overload=0'
            . ' phpexcelConvert.php ' . escapeshellarg($csvFileName) . ' '
            . escapeshellarg($xlsFileName) . ' '
            . escapeshellarg($delimiter) . ' '
            . escapeshellarg($csvEncoding);

        exec($command, $output, $returnVar);

        if (!count($output)
            || $returnVar > 0
        ) {
            return false;
        }

        return $xlsFileName;
    }

    /**
     * Dekodiert ein in PHP-Excel formatiertes Datum in ein normal lesbares Datum
     *
     * @param int $dateExcel Datum im Excelkodierten Format
     * @param string $datePattern Datepattern für Format des Rückgabedatum
     *
     * @return string
     */
    public function decodeExcelDate($dateExcel, $datePattern = 'd.m.Y')
    {
        $unixDate = ($dateExcel - 25569) * 86400;
        return gmdate($datePattern, $unixDate);
    }

    /**
     * @param string $filePath
     * @return array
     * @throws PHPExcel_Reader_Exception
     */
    public function getDataFromExcelFilePath($filePath){
        $inputFileType = PHPExcel_IOFactory::identify($filePath);
        $objReader = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel = $objReader->load($filePath);
        return $objPHPExcel->getActiveSheet()->toArray(null, true, true, false);
    }
}