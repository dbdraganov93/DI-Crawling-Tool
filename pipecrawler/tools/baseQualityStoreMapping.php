<?php

require_once '../crawlerOld/utils/Page.php';
require_once '../crawlerOld/utils/store.php';
require_once '../crawlerOld/utils/general.php';

$fh = fopen("quality.csv", 'r');

// erste Zeile = Kopfzeile
$line = fgetcsv($fh, 4096, ';');

$file = new StoreFile();

$storeCount = 10001;

while (($line = fgetcsv($fh, 4096, ';')) !== FALSE) {
    $store = new Store();
        
    $store->store_number = trim($line[1]);
       
    $store->distribution = 'Quality Partner';   

    $store->subtitle = 'BASE Quality Partner '. trim($line[2]);
    $store->street = trim($line[3]);
    $store->street_number = getStreetNumber($store->street);
    $store->zipcode = trim($line[4]);
    $store->city = trim($line[5]);
    $store->phone = trim($line[6]);
    $store->fax = trim($line[8]);
    $store->website = 'http://shopsuche.base.de/';
        
    $file->add($store);
}

$file->close();

?>
