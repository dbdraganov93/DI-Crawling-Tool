<?php

/**
 * Prospekt Crawler für Möbelstadt Rück (ID: 67367)
 */
class Crawler_Company_MoebelstadtRueck_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $strGTags = 'Wohnwand, Sideboard, Couch, Sessel, Beleuchtung, Geschirr, Bettwäsche, Bad, Schreibtisch, Büro';
        $strKTags = 'Spüle, Spülmaschine, Küchenzeile, Einbauküche, Kühlschrank, Herd, Ceranfeld, Ofen, Dunstesse';
        $strTSTags = 'Spiegel, Kommode, Schlafzimmer, Schrank, Couch, Tisch, Gartenmöbel, Stuhl, Bett';
        $aBrochureTitles = array(
            'TS' => 'Top Store',
            'G' => 'Möbel Angebote',
            'K' => 'Küchen Angebote'
        );

        $sFtp->connect($companyId);
        $localDirectory = $sFtp->generateLocalDownloadFolder($companyId);
        if (!$aFiles = $sFtp->listFiles()) {
            throw new Exception($companyId . ': no brochures available.');
        }

        $pattern = '#(.+?pdf)$#';
        $localFileNames = array();
        foreach ($aFiles as $sFile) {
            if (preg_match($pattern, $sFile)) {
                $localFileNames[$sFile] = $sFtp->downloadFtpToDir($sFile, $localDirectory);
            }
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($localFileNames as $localKey => $localValue) {
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $pattern = '#Rueck\_([A-Z]{1,2})([0-9]{6})\_([0-9]{6})\_([A-Z]{2})#';
            if (!preg_match($pattern, $localKey, $match)) {
                $this->_logger->err($companyId . ': invalid name scheme for ' . $localValue);
                continue;
            }
            $eBrochure->setTitle($aBrochureTitles[$match[1]])
                    ->setStart(preg_replace('#([0-9]{2})([0-9]{2})([0-9]{2})#', '$1.$2.$3', $match[2]))
                    ->setEnd(preg_replace('#([0-9]{2})([0-9]{2})([0-9]{2})#', '$1.$2.$3', $match[3]))
                    ->setVisibleStart(preg_replace('#([0-9]{2})([0-9]{2})([0-9]{2})#', '$1.$2.$3', $match[2]))
                    ->setStoreNumber(strtolower($match[4]))
                    ->setUrl($sFtp->generatePublicFtpUrl($localValue));

            switch ($match[1]) {
                case 'G': {
                        $eBrochure->setTags($strGTags);
                        break;
                    }
                case 'TS': {
                        $eBrochure->setTags($strTSTags);
                        break;
                    }
                case 'K': {
                        $eBrochure->setTags($strKTags);
                        break;
                    }
            }

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
