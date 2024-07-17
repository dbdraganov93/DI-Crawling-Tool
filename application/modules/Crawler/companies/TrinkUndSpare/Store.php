<?php
/**
 * Store Crawler for Trink&Spare (ID: 29133)
 */

 class Crawler_Company_TrinkUndSpare_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {

        $baseUrl = 'https://trink-und-spare.de/';

        $sPage = new Marktjagd_Service_Input_Page();
        
        $pageConf = $sPage->getPage();
        $pageConf->setMethod('POST');
        $pageConf->setUseCookies(true);
        
        $client = $pageConf->getClient();
        $client->setHeaders('X-Requested-With', 'XMLHttpRequest');
        $client->setHeaders('Origin', $baseUrl);
        $client->setHeaders('Referer', $baseUrl . 'marktsuche');
        $pageConf->setClient($client);
        
        $sPage->setPage($pageConf);

        $postParams = array(
            'zip' => '44649',
            'range' => '1000',
            'action' => 'get_markt'
        );
        
        $sPage->open($baseUrl . 'wp-admin/admin-ajax.php', $postParams);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach($jStores->db as $store) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setCity($store->ort)
                ->setStreetAndStreetNumber($store->strasse)
                ->setZipcode($store->plz)
                ->setPhone($store->tel1)
                ->setEmail($store->email)
                ->setLongitude($store->lng)
                ->setLatitude($store->lat)
                ->setWebsite($baseUrl . $store->surl)
                ->setStoreNumber($store->marktnr)
                ->setImage('https://trink-und-spare.de/wp-content/uploads/2017/07/markt-nv-header.jpg');
            
            $openingHours = 'Mo: ' . $store->oe_zeit_1_start . '-' . $store->oe_zeit_1_end;
            $openingHours .= ', Di: ' . $store->oe_zeit_2_start . '-' . $store->oe_zeit_2_end;
            $openingHours .= ', Mi: ' . $store->oe_zeit_3_start . '-' . $store->oe_zeit_3_end;
            $openingHours .= ', Do: ' . $store->oe_zeit_4_start . '-' . $store->oe_zeit_4_end;
            $openingHours .= ', Fr: ' . $store->oe_zeit_5_start . '-' . $store->oe_zeit_5_end;
            $openingHours .= ', Sa: ' . $store->oe_zeit_6_start . '-' . $store->oe_zeit_6_end;
            $eStore->setStoreHours($openingHours);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
     }
 }

 // Example Json response which was the foundation of this crawler
 /* {
	"zip": "44649",
	"range": 1000,
	"db": [{
		"id": "68",
		"marktnr": "16135",
		"bezeichnung": "Trink & Spare",
		"firma": "Trink & Spare Getr\u00e4nkefachm\u00e4rkte GmbH",
		"strasse": "G\u00fcldenwerth 39-43",
		"plz": "42857",
		"ort": "Remscheid",
		"bez_leiter": "3",
		"markt_leiter": "Herr Darweesh",
		"tel1": "+49 (2191) 5919641",
		"tel2": "",
		"fax": "",
		"email": "filiale_135@trink-und-spare.de",
		"oe_zeit_1_text": "",
		"oe_zeit_1_start": "08:00:00",
		"oe_zeit_1_end": "20:00:00",
		"oe_zeit_2_text": "",
		"oe_zeit_2_start": "08:00:00",
		"oe_zeit_2_end": "20:00:00",
		"oe_zeit_3_text": "",
		"oe_zeit_3_start": "08:00:00",
		"oe_zeit_3_end": "20:00:00",
		"oe_zeit_4_text": "",
		"oe_zeit_4_start": "08:00:00",
		"oe_zeit_4_end": "20:00:00",
		"oe_zeit_5_text": "",
		"oe_zeit_5_start": "08:00:00",
		"oe_zeit_5_end": "20:00:00",
		"oe_zeit_6_text": "",
		"oe_zeit_6_start": "08:00:00",
		"oe_zeit_6_end": "18:00:00",
		"bemerkung1": "",
		"bemerkung2": "",
		"bemerkung3": "",
		"bemerkung4": "",
		"bemerkung5": "",
		"bemerkung6": "",
		"lat": "51.1682108",
		"lng": "7.1620728",
		"regie": "1",
		"hz_teilnehmer": "1",
		"cd": "2016-05-10 00:00:00",
		"surl": "markt/remscheid-16135/"
	}, {
		"id": "163",
		"marktnr": "11290",
		"bezeichnung": "Trink & Spare",
		"firma": "Trink & Spare Getr\u00e4nkefachm\u00e4rkte GmbH",
		"strasse": "St. Huberter Stra\u00dfe 99",
		"plz": "47906",
		"ort": "Kempen",
		"bez_leiter": "4",
		"markt_leiter": "Herr Trogisch\r\n",
		"tel1": "",
		"tel2": "",
		"fax": "",
		"email": "filiale_290@trink-und-spare.de",
		"oe_zeit_1_text": "",
		"oe_zeit_1_start": "08:00:00",
		"oe_zeit_1_end": "20:00:00",
		"oe_zeit_2_text": "",
		"oe_zeit_2_start": "08:00:00",
		"oe_zeit_2_end": "20:00:00",
		"oe_zeit_3_text": "",
		"oe_zeit_3_start": "08:00:00",
		"oe_zeit_3_end": "20:00:00",
		"oe_zeit_4_text": "",
		"oe_zeit_4_start": "08:00:00",
		"oe_zeit_4_end": "20:00:00",
		"oe_zeit_5_text": "",
		"oe_zeit_5_start": "08:00:00",
		"oe_zeit_5_end": "20:00:00",
		"oe_zeit_6_text": "",
		"oe_zeit_6_start": "08:00:00",
		"oe_zeit_6_end": "18:00:00",
		"bemerkung1": "",
		"bemerkung2": "",
		"bemerkung3": "",
		"bemerkung4": "",
		"bemerkung5": "",
		"bemerkung6": "",
		"lat": "51.369833",
		"lng": "6.437083",
		"regie": "1",
		"hz_teilnehmer": "1",
		"cd": "2016-05-10 00:00:00",
		"surl": "markt/kempen-11290/"
    }, {...}
    ]
}
 */
