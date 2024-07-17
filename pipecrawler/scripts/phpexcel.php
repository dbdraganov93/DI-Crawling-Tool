<?php

chdir(__DIR__);

require_once __DIR__ . '/index.php';
ini_set('memory_limit', '1536M');

/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');
$isMapped = false;

if (count($argv) < 2 || !$argv[1]) {
    $logger->log($argv[0] . ' dateiname.xls [bool $mapHeadline] [delimiter for csv]', Zend_Log::INFO);
    $logger->log('Liest die übergebene CSV mit PHP-Excel ein.', Zend_Log::INFO);
    exit(1);
}


// Prüfen, ob Headline gemappt werden soll
if (count($argv) >= 3 && $argv[2] == '1'
) {
    $isMapped = true;
}

$inputFileName = $argv[1];
$inputFileType = PHPExcel_IOFactory::identify($inputFileName);
$objReader = PHPExcel_IOFactory::createReader($inputFileType);

if (strtolower($inputFileType) == 'csv' && count($argv) == 4
) {
    // Vor und nach dem Text noch ein Leerzeichen, da sonst Sonderzeichen am Anfang und Ende nicht erkannt werden.
    $charset = mb_detect_encoding(' ' . file_get_contents($inputFileName)
            . ' ', "UTF-8, ISO-8859-15, ISO-8859-1, windows-1252, ASCII");
    /* @var $objReader PHPExcel_Reader_CSV */
    $objReader->setDelimiter($argv[3]);
    $objReader->setInputEncoding($charset);
}
/* @var $objReader PHPExcel_Reader_Abstract */
$objReader->setReadDataOnly(false);

$savedValueBinder = PHPExcel_Cell::getValueBinder();
PHPExcel_Cell::setValueBinder(new PHPExcel_Cell_MyValueBinder());
/* @var $objPHPExcel PHPExcel */
$objPHPExcel = $objReader->load($inputFileName);
if (strtolower($inputFileType) == 'csv') {
    PHPExcel_Cell::setValueBinder($savedValueBinder);
}
$aWorksheets = array();

foreach ($objPHPExcel->getWorksheetIterator() as $worksheetCount => $worksheet) {
    /* @var $worksheet PHPExcel_Worksheet */
    $aWorksheet = array();
    $aWorksheet['title'] = $worksheet->getTitle();
    $aWorksheet['highestRow'] = $worksheet->getHighestRow();
    $aWorksheet['highestColumn'] = $worksheet->getHighestColumn();
    $aWorksheet['highestColumnIndex'] = PHPExcel_Cell::columnIndexFromString($aWorksheet['highestColumn']);

    $aMapHeadline = array();
    for ($row = 1; $row <= $aWorksheet['highestRow']; ++$row) {
        for ($col = 0; $col < $aWorksheet['highestColumnIndex']; ++$col) {
            $cell = $worksheet->getCellByColumnAndRow($col, $row);
            $val = $cell->getValue();

            if (PHPExcel_Shared_Date::isDateTime($cell)) {
                $val = date('d.m.Y', PHPExcel_Shared_Date::ExcelToPHP($val));
            }

            $val = $cell->getValue();
            if ((substr($val, 0, 1) === '=' ) && (strlen($val) > 1)) {
                $val = $cell->getOldCalculatedValue();
            }

            if ($isMapped) {
                if ($row == 1) {
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

echo json_encode($aWorksheets);