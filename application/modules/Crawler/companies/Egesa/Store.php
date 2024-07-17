<?php

/*
 * Store Crawler für egesa Zookauf (ID: 29000)
 */

class Crawler_Company_Egesa_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sMjFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sAddress = new Marktjagd_Service_Text_Address();

        $cStores = new Marktjagd_Collection_Api_Store();

        $sMjFtp->connect($companyId);

        $fileNameZookauf = $sMjFtp->downloadFtpToCompanyDir('zookauf_standorte.xlsx', $companyId);
        $fileNameCampaign = $sMjFtp->downloadFtpToCompanyDir('zookauf_kampagnenplan.xlsx', $companyId);
        $logoZookauf = $sMjFtp->downloadFtpToCompanyDir('zookauf_logo.jpg', $companyId);
        $storeData = $sExcel->readFile($fileNameZookauf, true)->getElement(0)->getData();
        $campaignData = $sExcel->readFile($fileNameCampaign, true)->getElement(0)->getData();
        
        foreach ($storeData as $store) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setSubtitle($store['Firmierung'])
                    ->setStreetAndStreetNumber($store['Adresse'])
                    ->setZipcode($store['PLZ'])
                    ->setCity($store['Ort'])
                    ->setPhoneNormalized($store['Telefon'])
                    ->setLogo($sMjFtp->generatePublicFtpUrl($logoZookauf));

            if ($eStore->getZipcode() == '46149') {
                $eStore->setDefaultRadius(5);
            }

            foreach ($campaignData as $singleCampaignData) {
                if ($sAddress->normalizeStreet($singleCampaignData['Strasse']) == $sAddress->normalizeStreet($store['Adresse'])) {
                    $eStore->setEmail($singleCampaignData['E-Mail']);
                    
                    $strDist = '';
                    if (strlen($singleCampaignData['Heimtierjournal / Themenkatalog Hund'])) {
                        if (strlen($strDist)) {
                            $strDist .= ',';
                        }
                        $strDist .= 'Heimtierjournal / Themenkatalog Hund ' . $singleCampaignData['Zeitraum_HTJ'];
                    }
                    
                    if (preg_match('#X#i', $singleCampaignData['RC Kampagne'])) {
                        if (strlen($strDist)) {
                            $strDist .= ',';
                        }
                        $strDist .= 'RC Kampagne ' . $singleCampaignData['Zeitraum'];
                    }
                    $eStore->setDistribution($strDist);
                }
            }

            if ($eStore->getZipcode() == '47178') {
                $eStore = $this->_addAddtionialStoreInfos($eStore);
            }
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileCsv = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileCsv);
    }

    /**
     * @param Marktjagd_Entity_Api_Store $eStore
     * @return Marktjagd_Entity_Api_Store
     */
    private function _addAddtionialStoreInfos($eStore)
    {
        $eStore->setPayment('Bar, EC-Kartenzahlung ab 10,00 Euro')
                ->setWebsite('http://www.muehle-dickmann.de/')
                ->setParking('50 kostenfreie Parkplätze. Behinderten Parkplatz vorhanden.')
                ->setBarrierFree(true)
                ->setBonusCard('Treuepass für Hunde- und Katzenpremiumfutter ab 10 kg. '
            . 'Treuepass für Nutztierfutter (Kaninchen, Geflügel, Pferde) ab 25 kg Sack.')
                ->setSection('Aquaristik, Hunde-, Katzen-, Nager-, Vogelabteilung, Floristik, '
            .'Garten- und Dekoabteilung, Pflanzenabteilung')
                ->setService('Lieferservice, Paket-Versand-Service, qualifizierte Beratung beim Heimtierkauf, '
            . 'Einrichtungsservice für Heimtierkäfige, Wassertest für Aquarium und Gartenteich, '
            . 'Vermittlung von Aquarienpflege qualifizierte Beratung bei Pflanzenschutz- und Düngemitteln, '
            . 'Sonderkonditionen bei Sammelbestellungen für Kleingartenvereine, Inspektionsservice für alle Gartengeräte'
            . 'qualifizierte Beratung bei Ihrer Gartengestaltung, Rollrasen – auf Wunsch mit Verlegung, '
            . 'Trauerfloristik – auch für Beerdigungsinstitute mit monatlicher Rechnungsstellung, '
            . 'kontaktieren Sie uns, wenn Ihre gewünschte Service-Leistung hier nicht aufgeführt ist')
                ->setToilet(true)
                ->setText('Die Mühle Dickmann in Duisburg ist ein moderner, inhabergeführter Einzelhandelsbetrieb. '
            . 'Seit 1906 stehen wir im Dienste unserer Kunden: kompetente Beratung, fachkundiger Service, '
            . 'freundliches und gut geschultes Personal, bestes Preis-Leistungsverhältnis!<br><br>'
            . 'Zu unseren Sortimentsschwerpunkten zählen "Ambiente und Floristik", "Garten – Alles für einen schönen '
            . 'Garten", "Heimtier – alles rund um`s Tier" und "Naturkost –Regionales".<br>'
            . 'Wir handeln partnerschaftlich gegenüber Mitarbeitern, Kunden und Lieferanten. Kooperationen sind ein '
            . 'wesentlicher Bestandteil unseres unternehmerischen Selbstverständnisses.<br>'
            . 'Regionalität, Qualität,  Nachhaltigkeit und Verantwortung  sind die Leitfäden unseres täglichen Handelns.<br>'
            . 'Wir sind neuen Entwicklungen gegenüber aufgeschlossen und sind bestrebt, uns stetig zu verbessern.')
                ->setDefaultRadius(3);

        return $eStore;
    }
}
