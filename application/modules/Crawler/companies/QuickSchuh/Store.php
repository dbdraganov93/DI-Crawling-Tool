<?php

/**
 * Storecrawler für Quick Schuh (ID: 67854)
 */
class Crawler_Company_QuickSchuh_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $url = 'http://www.quick-schuh-filiale.de/admin/marktjagd.de/qs-daten.php?p='
         . urlencode('Vr]mjxyzEcP19{3v2') . '&type=.csv';
        $cStore = new Marktjagd_Collection_Api_Store();

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($url);
        $page = $sPage->getPage()->getResponseBody();

        $jData = json_decode($page);

        foreach ($jData as $entry) {
            // only stores in germany
            if ( $entry->land != 'D' ) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $offers = array();

            if ( $entry->damen == 1 ) {
                $offers[] = 'Damen';
            }

            if ( $entry->herren == 1 ) {
                $offers[] = 'Herren';
            }

            if ( $entry->kinder == 1 ) {
                $offers[] = 'Kinder';
            }

            if ( $entry->sport == 1 ) {
                $offers[] = 'Sport';
            }


            $images = array();

            if ( strlen( $entry->bild0 ) ) {
                $images[] = $entry->bild0;
            }

            if ( strlen( $entry->bild1 ) ) {
                $images[] = $entry->bild1;
            }

            if ( strlen( $entry->bild2 ) ) {
                $images[] = $entry->bild2;
            }

            $hours = array();

            if ( strlen( $entry->mo1 ) ) {
                $hours[] = 'Mo ' . $entry->mo1 . '-' . $entry->mo2;
            }
            if ( strlen( $entry->mo3 ) ) {
                $hours[] = 'Mo ' . $entry->mo3 . '-' . $entry->mo4;
            }

            if ( strlen( $entry->di1 ) ) {
                $hours[] = 'Di ' . $entry->di1 . '-' . $entry->di2;
            }
            if ( strlen( $entry->di3 ) ) {
                $hours[] = 'Di ' . $entry->di3 . '-' . $entry->di4;
            }

            if ( strlen( $entry->mi1 ) ) {
                $hours[] = 'Mi ' . $entry->mi1 . '-' . $entry->mi2;
            }
            if ( strlen( $entry->mi3 ) ) {
                $hours[] = 'Mi ' . $entry->mi3 . '-' . $entry->mi4;
            }

            if ( strlen( $entry->do1 ) ) {
                $hours[] = 'Do ' . $entry->do1 . '-' . $entry->do2;
            }
            if ( strlen( $entry->do3 ) ) {
                $hours[] = 'Do ' . $entry->do3 . '-' . $entry->do4;
            }

            if ( strlen( $entry->fr1 ) ) {
                $hours[] = 'Fr ' . $entry->fr1 . '-' . $entry->fr2;
            }
            if ( strlen( $entry->fr3 ) ) {
                $hours[] = 'Fr ' . $entry->fr3 . '-' . $entry->fr4;
            }

            if ( strlen( $entry->sa1 ) ) {
                $hours[] = 'Sa ' . $entry->sa1 . '-' . $entry->sa2;
            }
            if ( strlen( $entry->sa3 ) ) {
                $hours[] = 'Sa ' . $entry->sa3 . '-' . $entry->sa4;
            }

            if ( strlen( $entry->so1 ) ) {
                $hours[] = 'So ' . $entry->so1 . '-' . $entry->so2;
            }
            if ( strlen( $entry->so3 ) ) {
                $hours[] = 'So ' . $entry->so3 . '-' . $entry->so4;
            }

            // doppelten Standort überspringen
            if ($entry->vnr == '576740')  {
                continue;
            }

            $eStore->setStoreNumber($entry->vnr)
                   ->setTitle($entry->name)
                   ->setSubtitle($entry->name2)
                   ->setStreetNumber($sAddress->extractAddressPart($entry->strasse, $sAddress::$EXTRACT_STREET_NR))
                   ->setStreet($sAddress->extractAddressPart($entry->strasse, $sAddress::$EXTRACT_STREET))
                   ->setZipcode($entry->plz)
                   ->setCity($entry->ort)
                   ->setPhone($sAddress->normalizePhoneNumber($entry->telefon))
                   ->setWebsite($entry->domain_www)
                   ->setEmail($entry->email)
                   ->setStoreHours(implode(',', $hours))
                   ->setPayment($entry->zahlungsmittel)
                   ->setImage(implode(',', $images));

            $cStore->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}