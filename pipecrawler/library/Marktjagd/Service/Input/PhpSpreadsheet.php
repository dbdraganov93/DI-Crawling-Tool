<?php

require_once APPLICATION_PATH . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell;
use PhpOffice\PhpSpreadsheet\Shared;

class Marktjagd_Service_Input_PhpSpreadsheet
{

    /**
     * @param $inputFileName
     * @param bool $isMapHeadline
     * @param null $delimiter
     * @param int $headlineRow
     * @return Marktjagd_Collection_PhpSpreadsheet_Worksheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function readFile($inputFileName, $isMapHeadline = false, $delimiter = null, $headlineRow = 1)
    {
        $cExcelSheet = new Marktjagd_Collection_PhpSpreadsheet_Worksheet();
        $inputFileType = IOFactory::identify($inputFileName);
        $objReader = IOFactory::createReader($inputFileType);

        if (strtolower($inputFileType) == 'csv') {
            // Vor und nach dem Text noch ein Leerzeichen, da sonst Sonderzeichen am Anfang und Ende nicht erkannt werden.
            $charset = mb_detect_encoding(' ' . file_get_contents($inputFileName)
                . ' ', "UTF-8, ISO-8859-15, ISO-8859-1, windows-1252, ASCII");
            $objReader->setDelimiter($delimiter);
            $objReader->setInputEncoding($charset);
        }
        /* @var $objReader PHPExcel_Reader_Abstract */
        $objReader->setReadDataOnly(false);

        $spreadsheet = $objReader->load($inputFileName);
        $aWorksheets = array();

        foreach ($spreadsheet->getAllSheets() as $worksheetCount => $worksheet) {
            $aWorksheet = [];
            $aWorksheet['title'] = $worksheet->getTitle();
            $aWorksheet['highestRow'] = $worksheet->getHighestRow();
            $aWorksheet['highestColumn'] = $worksheet->getHighestColumn();
            $aWorksheet['highestColumnIndex'] = Cell\Coordinate::columnIndexFromString($aWorksheet['highestColumn']);

            $aMapHeadline = [];
            for ($row = $headlineRow; $row <= $aWorksheet['highestRow']; ++$row) {
                for ($col = 1; $col <= $aWorksheet['highestColumnIndex']; ++$col) {
                    $cell = $worksheet->getCellByColumnAndRow($col, $row);
                    $val = $cell->getValue();

                    if ((substr($val, 0, 1) === '=') && (strlen($val) > 1)) {
                        $val = $cell->getOldCalculatedValue();
                    }
                    else if (Shared\Date::isDateTime($cell)) {
                        $val = date('d.m.Y', Shared\Date::excelToTimestamp($val));
                    }

                    if ($isMapHeadline) {
                        if ($row == $headlineRow) {
                            $aMapHeadline[$col] = $val;
                        } else {
                            $aWorksheet['data'][($row - 2)][$aMapHeadline[$col]] = $val;
                        }
                    } else {
                        $aWorksheet['data'][($row - 1)][$col] = $val;
                    }
                }
            }
            $aWorksheets[$worksheetCount] = $aWorksheet;
        }

        foreach ($aWorksheets as $key => $excelSheet) {
            $eExcelSheet = new Marktjagd_Entity_PhpSpreadsheet_Worksheet();
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
     * @param $inputFile
     * @param $outputFile
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function convertTableFile($inputFile, $outputFile)
    {
        $inputEnding = IOFactory::identify($inputFile);
        $inputReader = IOFactory::createReader($inputEnding);

        $inputObject = $inputReader->load($inputFile);

        if (!preg_match('#\.([^\.]+)$#', $outputFile, $outputEndingMatch)) {
            throw new Exception(': no file ending found.');
        }

        $outputWriter = IOFactory::createWriter($inputObject, ucwords($outputEndingMatch[1]));

        $outputWriter->save($outputFile);
    }

    /**
     * Dekodiert ein in PHP-Spreadsheet formatiertes Datum in ein normal lesbares Datum
     *
     * @param int $dateExcel Datum im Excel-kodierten Format
     * @param string $datePattern Datepattern für Format des Rückgabedatum
     *
     * @return string
     */
    public function decodeExcelDate($dateExcel, $datePattern = 'd.m.Y')
    {
        $unixDate = ($dateExcel - 25569) * 86400;
        return gmdate($datePattern, $unixDate);
    }
}