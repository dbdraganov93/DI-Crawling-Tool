#!/usr/bin/php -d extension=/home/niko.klausnitzer/framework/tools/pdftron/PDFNetPHP.so
<?php
/*
 * Hinzufügen eines Links auf einer oder mehrerer PDF-Seite(n)
 *
 * Nutzung: ./addSameLink.php <lokaler PDF Pfad> <lokaler Pfad neue PDF Datei> <Link> [1]
 *
 * Wenn die 1 am Ende gesetzt wird, so wird der Link auf alle Seiten eingefügt
 */
include("PDFNetPHP.php");
PDFNet::Initialize('Marktjagd GmbH(marktjagd.de):ENTCPU:1::L:AMS(20140303):EB4FEC423C0F78B962E2400F400DD2EC55CB1CD68AAD0430CE54C2BEF5C7'); // The parameter is the license key.

// Hauptprogramm
try {
    $pdfSource = $argv[1];
    $pdfDest = $argv[2];
    $link = json_decode(base64_decode($argv[3]));

    $isAllPages = false;
    if (array_key_exists(4, $argv)
        && $argv[4] == '1') {
        $isAllPages = true;
    }

    $doc = new PDFDoc($pdfSource);
    $doc->InitSecurityHandler();

    $startX = 0;
    $startY = 0;
    $endY = 60;

    $maxPage = (int) $doc->GetPageCount();
    for ($pageNo = 1; $pageNo <= $maxPage; $pageNo++) {
        $page = $doc->GetPage($pageNo);
        $pageHeight = $page->GetPageHeight();
        $pageWidth = $page->GetPageWidth();
        $borderStyle = new BorderStyle(BorderStyle::e_solid, 0.0, 0.0, 0.0);
        $hyperLink = Link::Create(
            $doc->GetSDFDoc(),
            new Rect(
                (float) $startX,
                (float) $pageHeight - (float) $startY,
                (float) $pageWidth,
                (float) $pageHeight - (float) $endY),
            Action::CreateURI($doc->GetSDFDoc(), $link));
        $hyperLink->setBorderStyle($borderStyle);
        $page->AnnotPushBack($hyperLink);

        if (!$isAllPages) {
            break;
        }
    }


    $doc->Save($pdfDest, SDFDoc::e_compatibility);
} catch (Exception $e) {
    echo $e->getMessage();
    exit(1);
}
