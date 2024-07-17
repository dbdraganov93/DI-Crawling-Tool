#!/usr/bin/php -d extension=/home/niko.klausnitzer/framework/tools/pdftron/PDFNetPHP.so
<?php
/*
 * Extrahieren des Text einer Seite von einem PDF
 * Nutzung: ./extractLinks.php <lokaler PDF Pfad Source> <lokaler PDF Pfad Destination> <BASE64 encodierter preg_match> <BASE64 encodierter Replace String>
 */
include('PDFNetPHP.php');
PDFNet::Initialize(); // The parameter is the license key.

// Hauptprogramm
try {
    if($argv[1]) {
        $aSearch = json_decode(base64_decode($argv[3]));
        $aReplace = json_decode(base64_decode($argv[4]));

        $doc = new PDFDoc($argv[1]);
        $doc->InitSecurityHandler();

        for ( $itr = $doc->GetPageIterator(); $itr->HasNext(); $itr->Next() ) {
            $page = $itr->Current();
            $num_annots = $page->GetNumAnnots();

            for ($i=0; $i<$num_annots; ++$i)
            {
                $annot = $page->GetAnnot($i);
                if (!$annot->IsValid()) continue;
                if ($annot->GetType() == Annot::e_Link) {
                    $link = new Link($annot);
                    $action = $link->GetAction();
                    if (!$action->IsValid()) continue;
                    if ($action->GetType() == Action::e_URI) {
                        $newUri = $uri = $action->GetSDFObj()->Get("URI")->Value()->GetAsPDFText();

                        foreach ($aSearch as $key => $search) {
                            if (preg_match($search, $uri, $match)) {
                                $newUri = $aReplace[$key] . urlencode($match[1]);
                            }

                            $action = $action->CreateURI($action->GetSDFObj()->GetDoc(), $newUri);
                            $link->SetAction($action);
                        }
                    }
                }
            }
        }

        $doc->Save($argv[2], SDFDoc::e_compatibility);
    }
    else {
        echo 'PDFTron-Engine: no PDF-File' . "\n";
        exit(1);
    }

} catch (Exception $e) {
    echo $e->getMessage();
    exit(1);
}