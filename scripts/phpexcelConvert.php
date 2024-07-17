<?php
chdir(__DIR__);

require_once __DIR__ . '/index.php';
ini_set('memory_limit', '1536M');

/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');
$isMapped = false;

if (count($argv) < 4
    || !$argv[1]) {
    $logger->log($argv[0] . ' dateiname.csv dateiname.xls delimiter [encoding of csv file]', Zend_Log::INFO);
    $logger->log('Liest die übergebene CSV mit PHP-Excel ein und konvertiert diese in xls', Zend_Log::INFO);
    exit(1);
}

$csv_file = $argv[1];
$xls_file = $argv[2];
$delimiter = $argv[3];
$csv_enc  = null;

if (array_key_exists(4, $argv)) {
    $csv_enc = $argv[4];
}

//set cache
$cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
PHPExcel_Settings::setCacheStorageMethod($cacheMethod);

//open csv file
$objReader = new PHPExcel_Reader_CSV();
$objReader->setDelimiter($delimiter);
if ($csv_enc != null) {
    $objReader->setInputEncoding($csv_enc);
}
$objPHPExcel = $objReader->load($csv_file);
$in_sheet = $objPHPExcel->getActiveSheet();

//open excel file
$objPHPExcel = new PHPExcel();
$out_sheet = $objPHPExcel->getActiveSheet();

//row index start from 1
$row_index = 0;
foreach ($in_sheet->getRowIterator() as $row) {
    $row_index++;
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);

    //column index start from 0
    $column_index = -1;
    foreach ($cellIterator as $cell) {
        $column_index++;
        $out_sheet->setCellValueByColumnAndRow($column_index, $row_index, $cell->getValue());
    }
}

//write excel file
$objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
$objWriter->save($xls_file);

echo $xls_file;