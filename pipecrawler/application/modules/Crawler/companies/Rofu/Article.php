<?php

/**
 * Artikelcrawler für Rofu (ID: 28773)
 */
class Crawler_Company_Rofu_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $cArticle = new Marktjagd_Collection_Api_Article();


        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $csvFiles = array();
        $sFtp->connect($companyId);
        $sFtp->changedir(date('Y-m'));
        foreach ($sFtp->listFiles() as $singleFile) {
            // only handle csv files
            if (preg_match('#.*\.csv$#', $singleFile)) {
                $csvFiles[] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        // gather data from each file
        foreach ($csvFiles as $csvFile) {
            $header = NULL;
            $data = array();

            // read file
            $handle = fopen($csvFile, 'r');
            while (($row = fgetcsv($handle, 10000, '|')) !== FALSE) {
                if (!$header) {
                    $header = $row;
                } else {
                    // skip invalid csv lines
                    if (sizeof($header) == sizeof($row)) {
                        $data[] = array_combine($header, $row);
                    }
                }
            }
            fclose($handle);

            foreach ($data as $line) {
                if (!strlen($line['article_number'])) {
                    continue;
                }

                $eArticle = new Marktjagd_Entity_Api_Article();

                $tags = preg_split('#\s#', $line['tags']);
                $categories = preg_split('#\s>\s#', $line['shop_category']);

                $tags = array_merge($tags, $categories);

                // remove empty tags
                foreach ($tags as $key => $value) {
                    if (strlen($value) == 0) {
                        unset($tags[$key]);
                    }
                }

                $hintPattern = array(
                    '#\[\{\s?oxcontent\s?ident=(\")+warnung3jahre(\")+\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=(\")+warnungschutzausruestung(\")+\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=(\")+achtunghausgebrauch(\")+\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=(\")+3jahrestrangulation(\")+\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=(\")+achtungchemie8jahre(\")+\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=(\")+achtungschnuere(\")+\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=(\")+achtungwasser(\")+\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=(\")+achtungduftstoffe(\")+\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=(\")+achtungfunktion(\")+\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=(\")+achtunglebensmittel(\")+\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=(\")+achthttp://www.atelco.de/ai/export/marktjagd_stores.csvungschutzmaske(\")+\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=(\")+carreralegal(\")+\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=fillytraumschloss\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=fillyturm\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=fillypluesch\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=chichiloveshowstar\s?\}\]#',
                    '#\[\{\s?oxcontent\s?ident=imc_barbie\s?\}\]#'
                );

                $hintReplace = array(
                    'Achtung! Nicht geeignet für Kinder unter 3 Jahren. Erstickungsgefahr durch abbrechbare, verschluckbare Kleinteile. ',
                    'Achtung! Mit Schutzausrüstung zu benutzen. Nicht im Straßenverkehr zu verwenden. ',
                    'Achtung! Nur für den Hausgebrauch. Benutzung unter unmittelbarer Aufsicht von Erwachsenen. ',
                    'Achtung! Nicht geeignet für Kinder unter 3 Jahren. Strangulationsgefahr.  ',
                    'Achtung! Nicht geeignet für Kinder unter 8 Jahren. Benutzung unter Aufsicht von Erwachsenen. ',
                    'Achtung! Um mögliche Verletzungen durch Verheddern zu verhindern, ist dieses Spielzeug zu entfernen, wenn das Kind beginnt, auf allen vieren zu krabbeln. ',
                    'Achtung! Nur im flachen Wasser unter Aufsicht von Erwachsenen verwenden. ',
                    'Achtung! Enthält Duftstoffe, die Allergien auslösen können. ',
                    'Achtung! Benutzung unter unmittelbarer Aufsicht von Erwachsenen. ',
                    'Achtung! Enthält Spielzeug. Beaufsichtigung durch Erwachsene empfohlen. ',
                    'Achtung! Dieses Spielzeug bietet keinen Schutz. ',
                    '',
                    '',
                    '',
                    '',
                    '',
                    ''
                );

                $text = preg_replace($hintPattern, $hintReplace, $line['text']);
                $text = preg_replace('#\[\{ oxcontent ident=[^"^\}]\}\]#', '', $text);

                $text .= '<br><br><strong>Das dargestellte Angebot gilt beim ROFU-Onlineshop MIFUS.de (zzgl. Versandkosten). In den ROFU-Filialen kann der Preis abweichen. Bitte erfragen Sie die dort die Verfügbarkeit. Vielen Dank für Ihr Verständnis.</strong>';

                $title = $line['title'];

                if (!preg_match('#' . $line['trademark'] . '#i', $title)) {
                    $title = $line['trademark'] . " - " . $title;
                }

                $eArticle->setArticleNumber($line['article_number'])
                    ->setTitle($title)
                    ->setPrice(trim($line['price']))
                    ->setText($text)
                    ->setEan($line['ean'])
                    ->setManufacturer($line['manufacturer'])
                    ->setArticleNumberManufacturer($line['article_number_manufacturer'])
                    ->setSuggestedRetailPrice(trim($line['suggested_retail_price']))
                    ->setTrademark($line['trademark'])
                    ->setTags((count($tags)) ? implode(',', $tags) : '')
                    ->setImage($line['image'])
                    ->setStart($line['start'])
                    ->setEnd($line['end'])
                    ->setVisibleStart($line['visible_start'])
                    ->setUrl($line['url']);

                $cArticle->addElement($eArticle);
            }
        }

        return $this->getResponse($cArticle, $companyId);
    }
}
