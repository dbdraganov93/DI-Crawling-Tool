<?php

/**
 * Store Crawler für tejo's SB Lagerkauf (ID: 29240)
 */
class Crawler_Company_TejoLagerkauf_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        # Tejo bezieht seine Standortdaten von yext und embedded diese nur, daher hier der Direktlink zu Yext
        $searchUrl = 'https://knowledgetags.yextpages.net/embed?key=l7TaqsNkV890EfI55Ea59Gqh1Cff7KMwoFWIbEmD79pGtpr70hQnrdmHrwqpsgiZ&account_id=7881266292074191352&entity_id=52&entity_id=69&entity_id=28&entity_id=68&entity_id=44&entity_id=65&entity_id=62&entity_id=66&entity_id=59&entity_id=32&entity_id=33&entity_id=25&entity_id=23&entity_id=38&entity_id=63&entity_id=37&entity_id=34&entity_id=67&entity_id=36';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $pStores = $sPage->getPage()->getResponseBody();
        $pattern = "#Yext\._embed\(([^\)]+?)\)#";

        # Store-Daten sind in JSON als Funtionsparameter einer javascript-function vorhanden
        if (!preg_match_all($pattern, $pStores, $storeJSON)) {
            throw new Exception($companyId . ' - no JSON found at ' . $searchUrl);
        }

        # nur die 1. Capture-Group (das gesamt JSON)
        $storeJSON = json_decode($storeJSON[1][0]);

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach($storeJSON->entities as $store) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $this->_logger->info("Found Store " . $store->attributes->name . " in " .$store->attributes->city);

            $eStore->setStoreHoursNormalized(implode(", ",$store->attributes->hours))
                ->setStreetAndStreetNumber($store->attributes->address1)
                ->setSubtitle($store->attributes->name)
                ->setCity($store->attributes->city)
                ->setZipcode($store->attributes->zip)
                ->setFaxNormalized($store->attributes->fax)
                ->setPhoneNormalized($store->attributes->phone)
                ->setEmail($store->attributes->email)
                ->setLatitude($store->schema->geo->latitude)
                ->setLongitude($store->schema->geo->longitude)
                ->setWebsite($store->schema->url)
                ->setStoreNumber($store->entityId);

            $cStores->addElement($eStore);
        }

    return $this->getResponse($cStores, $companyId);
    }
}