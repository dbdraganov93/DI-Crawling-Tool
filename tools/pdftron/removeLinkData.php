#!/usr/bin/php -d extension=/home/pdftron/PDFNetPHP.so
<?php
/*
 * Löschen eines Links einer Seite von einem PDF
 * Nutzung: ./removeLinkData.php <lokaler PDF Pfad Source> <lokaler PDF Pfad Destination> <BASE64 encodierter preg_match>
 */
include("PDFNetPHP.php");
PDFNet::Initialize(); // The parameter is the license key.

// Hauptprogramm
try {
    if ($argv[1]) {
        $aSearch = json_decode(base64_decode($argv[3]));

        $doc = new PDFDoc($argv[1]);
        $doc->InitSecurityHandler();

        for ($itr = $doc->GetPageIterator(); $itr->HasNext(); $itr->Next()) {
            $page = $itr->Current();
            $num_annots = $page->GetNumAnnots();
            for ($i = 0; $i < $num_annots; ++$i) {
                $annot = $page->GetAnnot($i);
                if (!$annot->IsValid() || $annot->GetType() != Annot::e_Link)
                    continue;
                $link = new Link($annot);
                $action = $link->GetAction();
                if (!$action->IsValid() || $action->GetType() != Action::e_URI)
                    continue;
                $uri = $action->GetSDFObj()->Get("URI")->Value()->GetAsPDFText();
                foreach ($aSearch as $key => $search) {
                    if (preg_match($search, $uri)) {
                        $page->AnnotRemove($i);
                    }
                }
            }
        }

        $doc->Save($argv[2], SDFDoc::e_compatibility);
    } else {
        echo 'PDFTron-Engine: no PDF-File' . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo $e->getMessage();
    exit(1);
}
