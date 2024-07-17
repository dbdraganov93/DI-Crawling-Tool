#!/usr/bin/php -d extension=/home/niko.klausnitzer/framework/tools/pdftron/PDFNetPHP.so
<?php
/*
 * Extrahieren des Text einer Seite von einem PDF
 * Nutzung: ./extractLinks.php <lokaler PDF Pfad> <link|text|info> <Seite>
 */
include("PDFNetPHP.php");
PDFNet::Initialize('Marktjagd GmbH(marktjagd.de):ENTCPU:1::L:AMS(20140303):EB4FEC423C0F78B962E2400F400DD2EC55CB1CD68AAD0430CE54C2BEF5C7'); // The parameter is the license key.

try {
    $pdfSource = $argv[1];
    $pdfDest = $argv[2];
    $csvSource = $argv[3];
    $origin = false;
    if (!array_key_exists(4, $argv)
        || $argv[4] == 'bot') {
        $origin = true;
    }

    $header = array();
    $aLinkCoords = array();

    $doc = new PDFDoc($pdfSource);

    $csvData = fopen($csvSource, 'r');

    while (($csvLine = fgetcsv($csvData, '0', ';')) != FALSE) {
        if (empty($header)) {
            $header = $csvLine;
            continue;
        }
        $aLinkCoords[] = array_combine($header, $csvLine);
    }
    fclose($csvData);

    foreach ($aLinkCoords as $key => $aSingleCoord) {
        $page = $doc->GetPage($aSingleCoord['pageNo']);
        $pageHeight = $page->GetPageHeight();
        $pageWidth = $page->GetPageWidth();
        $givenPageHeight = $aSingleCoord['pageHeight'];
        $givenPageWidth = $aSingleCoord['pageWidth'];
        if (($heightScaling = (float) $givenPageHeight / (float) $pageHeight) <= 0) {
            $heightScaling = 1;
        }
        if (($widthScaling = (float) $givenPageWidth / (float) $pageWidth) <= 0) {
            $widthScaling = 1;
        }
        $borderStyle = new BorderStyle(BorderStyle::e_solid, 0.0, 0.0, 0.0);

        $startY = (float) $aSingleCoord['startY'] / $heightScaling;
        $endY = (float) $aSingleCoord['endY'] / $heightScaling;

        if (!$origin) {
            $startY = (float) $pageHeight - (float) $aSingleCoord['startY'] / $heightScaling;
            $endY = (float) $pageHeight - (float) $aSingleCoord['endY'] / $heightScaling;
        }
        $hyperLink = Link::Create($doc->GetSDFDoc(),
                new Rect(
                (float) $aSingleCoord['startX'] / $widthScaling,
                $startY,
                (float) $aSingleCoord['endX'] / $widthScaling,
                $endY,
                Action::CreateURI($doc->GetSDFDoc(),
                        $aSingleCoord['link'])));
        $hyperLink->setBorderStyle($borderStyle);
        $page->AnnotPushBack($hyperLink);
    }
    $doc->Save($pdfDest, SDFDoc::e_compatibility);
} catch (Exception $e) {
    echo $e->getMessage();
    exit(1);
}