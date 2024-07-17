<?php

require_once '../crawlerOld/utils/Page.php';
require_once '../crawlerOld/utils/store.php';
require_once '../crawlerOld/utils/general.php';

$fh = fopen("standorte.csv", 'r');

// erste Zeile = Kopfzeile
$line = fgetcsv($fh, 4096, ';');

$file = new StoreFile();

$storeCount = 10001;

while (($line = fgetcsv($fh, 4096, ';')) !== FALSE) {
    $store = new Store();
        
    $store->store_number = trim($line[1]);
    
    if (preg_match('#farmer#i', trim($line[0]))){
        $store->distribution = 'Farmer,Attacker&Farmer';
    }elseif (preg_match('#attacke#i', trim($line[0]))){
        $store->distribution = 'Attacker,Attacker&Farmer';
    }

    $store->subtitle = trim($line[2]);
    $store->street = trim($line[3]);
    $store->street_number = getStreetNumber($store->street);
    $store->zipcode = trim($line[4]);
    $store->city = trim($line[5]);
    $store->phone = trim($line[6]);
    $store->fax = trim($line[7]);
    $store->website = 'http://shopsuche.base.de/';
    
    $hourStr= '';
    
    $weekdays = array ( '0' => 'Mo',
                        '1' => 'Di',
                        '2' => 'Mi',
                        '3' => 'Do',
                        '4' => 'Fr',
                        '5' => 'Sa',
                        '6' => 'So'
                        );
    
    foreach ($weekdays as $step => $day){        
        $hourAr = array();
        for ($i=0 ; $i<=3; $i++){
            $index = 8 + $step*4 + $i;
            
            var_dump($line[$index]);
            
            if (strlen(trim($line[$index])) && preg_match('#[0-9]:[0-9]#', $line[$index])){
                $hourAr[] = trim($line[$index]);
            }                           
        }             
        
        for ($n = 0; $n <= 2; $n=$n+2){
            if ($hourAr[$n]){
                $hourStr .= $day . ' ' . $hourAr[$n] . '-' . $hourAr[$n+1] . ',';
            }
        }
    }
    
    $hourStr = substr($hourStr, 0, strlen($hourStr)-1);    
    
    $store->store_hours = $hourStr;
    
    $file->add($store);
}

$file->close();

?>
