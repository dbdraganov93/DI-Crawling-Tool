<?php
/**
 * Store Crawler für Basic (ID: 81135)
 */

class Crawler_Company_Basic_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $searchUrl = 'https://basicbio.de/de-DE/Maerkte';
        $sPage = new Marktjagd_Service_Input_Page();

        $DOMDocument = $sPage->getResponseAsDOM($searchUrl);
        $xpath = new DOMXPath($DOMDocument);

        $query = '/html/body/div[5]/div[3]/*/*/div/*/div[1]/p';
        $entries = $xpath->query($query);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($entries as $entry) {
            // TODO: This logic to extract the store information does not work yet
            $exploded = explode('<br>', preg_replace(['#\s\s#', '$&#xD;$', '#</br>#'], '', $entry->C14N()));
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStreetAndStreetNumber($exploded[1])
                ->setZipcodeAndCity($exploded[2])
                ->setPhoneNormalized(str_replace('Telefon:', '', $exploded[5]));

            for ($i=0; $i < count($exploded); $i++) {
                if (strpos($exploded[$i], 'Öffnungszeiten')) {
                    $eStore->setStoreHoursNormalized($exploded[$i+1])
                        ->setStoreHoursNotes($exploded[$i+2]);
                }
            }

            $cStores->addElement($eStore);
        }
        return $this->getResponse($cStores, $companyId);
    }
}
