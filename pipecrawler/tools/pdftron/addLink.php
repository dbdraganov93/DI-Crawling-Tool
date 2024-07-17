#!/usr/bin/php -d extension=/home/niko.klausnitzer/framework/tools/pdftron/PDFNetPHP.so
<?php
/*
 * Extrahieren des Text einer Seite von einem PDF
 * Nutzung: ./extractLinks.php <lokaler PDF Pfad> <link|text|info> <Seite>
 */
include("PDFNetPHP.php");
PDFNet::Initialize('Marktjagd GmbH(marktjagd.de):ENTCPU:1::L:AMS(20140303):EB4FEC423C0F78B962E2400F400DD2EC55CB1CD68AAD0430CE54C2BEF5C7'); // The parameter is the license key.

// Hauptprogramm
try {

    $fileName = $argv[1];
    if ($fileName) {
        $localPath = '/home/niko.klausnitzer/framework/tools/pdftron/';
        $zip = new ZipArchive();
        if ($zip->open($fileName) == TRUE) {
            $zip->extractTo($localPath);
            $zip->close();
        }
        $pdfFile = '';
        $pattern = '#([^/]*hornbach.+)\.zip$#i';
        if (preg_match($pattern, $fileName, $aNameMatch)) {
            $fileName = $aNameMatch[1];
        }
        $localPath = $localPath . preg_replace('#hornbach\_#i', '', $fileName);
        $fileHandle = opendir($localPath . "/PDF/");
        $pattern = '#\.pdf$#';
        while (($file = readdir($fileHandle)) != FALSE) {
            if (preg_match($pattern, $file)) {
                $pdfFiles[] = $localPath . "/PDF/" . $file;
            }
        }
        foreach ($pdfFiles as $pdfFile) {
            $doc = new PDFDoc($pdfFile);
            $doc->InitSecurityHandler();
            $fileName = preg_replace('#hornbach\_#i', '', $fileName);
            $pattern = '#' . $fileName . '_(.+?)\.pdf#';
            if (preg_match($pattern, $pdfFile, $pdfMatch)) {
                chdir($localPath . '/XML/' . $pdfMatch[1] . '/');
            }
            $fileHandle = opendir(getcwd());
            $count = 0;
            while (($file = readdir($fileHandle)) != FALSE) {
                if (preg_match('#[0-9]+\.xml$#', $file, $aXmlMatch)) {
                    $aXml[] = $aXmlMatch[0];
                }
            }
            foreach ($aXml as $singleXml) {
                $xml = simplexml_load_file($singleXml);
                $givenPageHeight = $xml->attributes()->height;
                $givenPageWidth = $xml->attributes()->width;
                foreach ($xml as $xSingleObject) {
                    if ($xSingleObject->alink->attributes()->target == '_onlinekatpage') {
                        continue;
                    }
                    $link = 'http:/' . '/' . 'www.hornbach.de/shop/article/showArticle.html?articleNo='
                        . $xSingleObject->alink->attributes()->href . '&WT.mc_id=de14ep002';
                    if ($xSingleObject->alink->attributes()->specialIcons == '_link') {
                        $link = $xSingleObject->alink->attributes()->href . '?WT.mc_id=de14ep002';
                    }
                    $aStartCoords = $xSingleObject->coords[0]->attributes();
                    $aEndCoords = $xSingleObject->coords[1]->attributes();
                    $startX = '';
                    $startY = '';
                    $endX = '';
                    $endY = '';

                    foreach ($aStartCoords as $a => $b) {
                        if ($a == 'x') {
                            $startX = $b;
                        }
                        if ($a == 'y') {
                            $startY = $b;
                        }
                    }

                    foreach ($aEndCoords as $a => $b) {
                        if ($a == 'x') {
                            $endX = $b;
                        }
                        if ($a == 'y') {
                            $endY = $b;
                        }
                    }
                    $pattern = '#([0-9]+)\.xml#';
                    if (preg_match($pattern, $singleXml, $aPageMatch)) {
                        $page = $doc->GetPage($aPageMatch[1]);
                    }
                    $pageHeight = $page->GetPageHeight();
                    $pageWidth = $page->GetPageWidth();
                    $heightScaling = (float)$givenPageHeight / (float)$pageHeight;
                    $widthScaling = (float)$givenPageWidth / (float)$pageWidth;
                    $borderStyle = new BorderStyle(BorderStyle::e_solid, 0.0, 0.0, 0.0);
                    $hyperLink = Link::Create($doc->GetSDFDoc(), new Rect((float)$startX / $widthScaling, (float)$pageHeight - (float)$startY / $heightScaling, (float)$endX / $widthScaling, (float)$pageHeight - (float)$endY / $heightScaling),
                        Action::CreateURI($doc->GetSDFDoc(), $link));
                    $hyperLink->setBorderStyle($borderStyle);
                    $page->AnnotPushBack($hyperLink);
                }
            }
            $doc->Save("/home/niko.klausnitzer/framework/tools/pdftron/" . $fileName . "_" . $pdfMatch[1] . "_linked.pdf", SDFDoc::e_compatibility);
        }
    }
} catch (Exception $e) {
    echo $e->getMessage();
    exit(1);
}
