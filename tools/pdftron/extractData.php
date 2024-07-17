#!/usr/bin/php -d extension=/home/niko.klausnitzer/framework/tools/pdftron/PDFNetPHP.so
<?php
/*
 * Extrahieren des Text einer Seite von einem PDF
 * Nutzung: ./extractLinks.php <lokaler PDF Pfad> <link|text|info> <Seite>
 */
include("PDFNetPHP.php");
PDFNet::Initialize('Marktjagd GmbH(marktjagd.de):ENTCPU:1::L:AMS(20140303):EB4FEC423C0F78B962E2400F400DD2EC55CB1CD68AAD0430CE54C2BEF5C7'); // The parameter is the license key.

/*
 * Prüft die Link-Box ob sie innerhalb der Seite liegt.
 */
function checkBox(&$bbox, $width, $height) {
	if($bbox->y2 > $height) {
    	$bbox->y2 = $height;
  	}
  	if($bbox->x2 > $width) {
    	$bbox->x2 = $width;
  	}
}

/*
 * Extrahiert Text aus einer PDF-Seite.
 */
function extractText($page) {
	if ($page){
		$txt = new TextExtractor();
		$txt->Begin($page);
		$result = $txt->GetAsText();
	}
	return $result;
}

/*
 * Extrahiert Links aus einer PDF-Seite.
 */
function extractLink($page) {
	if ($page){
		$numAnnots = $page->GetNumAnnots();
		$links = array();

		for ($i=0; $i<$numAnnots; ++$i)
		{
			$annot = $page->GetAnnot($i);
			if ($annot->IsValid()) {
				if($annot->GetType() == Annot::e_Link) {
					$lk = new Link($annot);
					$action = $lk->GetAction();

					if ($action->GetType() == Action::e_URI && $action->IsValid()) {
						$bbox = $annot->GetRect();

						checkBox($bbox, $page->GetPageWidth(), $page->GetPageHeight());

						$link['number'] = null;
						$link['url'] = $action->GetSDFObj()->Get("URI")->Value()->GetAsPDFText();
						$link['top'] = round(($page->GetPageHeight()-$bbox->y2)/$page->GetPageHeight(),4);
						$link['left'] = round($bbox->x1/$page->GetPageWidth(),4);
						$link['height'] = round(($bbox->y2 - $bbox->y1)/$page->GetPageHeight(),4);
						$link['width'] = round(($bbox->x2 - $bbox->x1)/$page->GetPageWidth(),4);
						$link['height'] = $link['height'] > 1 ? 1 : $link['height'];
						$link['width'] = $link['width'] > 1 ? 1 : $link['width'];

						$links[$bbox->x1.$bbox->x2.$bbox->y1.$bbox->y2] = $link;
					}
				}
			}
		}
		$result = array_values($links);

		if(!empty($result)) {
			$number=1;
			foreach($result as &$link) {
				$link['number'] = $number;
				$number++;
			}
		}
	}

print_r($result); die();

	return $result;
}

/*
 * Extrahiert Informationen aus einem PDF.
 */
function extractInfo($doc) {
	$data['pages'] = (int)$doc->GetPageCount();
	return $data;
}

// Hauptprogramm:
try {
	if($argv[1]) {
		$doc = new PDFDoc($argv[1]);
		$doc->InitSecurityHandler();

		switch ($argv[2]) {
			case 'link':
				$output = json_encode(extractLink($doc->GetPage((int)$argv[3])));
				break;

			case 'text':
				$output = extractText($doc->GetPage((int)$argv[3]));
				break;

			case 'info':
				$output = json_encode(extractInfo($doc));
				break;

			default:
				echo 'PDFTron-Engine: no korrekt type';
				exit(1);
		}
		echo $output;
		exit(0);
	}
	else {
		echo 'PDFTron-Engine: no PDF-File';
		exit(1);
	}

} catch (Exception $e) {
	echo $e->getMessage();
	exit(1);
}
