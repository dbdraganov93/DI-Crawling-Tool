#!/usr/bin/php
<?php
chdir(__DIR__);

require_once '../scripts/index.php';

/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');

$baseUrl = 'http://www.wer-zu-wem.de/';

/*
$aUrls = array(  
$baseUrl . 'dienstleister/coffee-shops.html',
$baseUrl . 'dienstleister/pizza-service.html',
$baseUrl . 'dienstleister/systemgastronomie.html',
$baseUrl . 'dienstleister/themengastronomie.html',
$baseUrl . 'dienstleister/hotelketten.html',
$baseUrl . 'dienstleister/autovermieter.html',
$baseUrl . 'dienstleister/banken.html',
$baseUrl . 'dienstleister/sparkassen.html',
$baseUrl . 'dienstleister/volksbanken.html'
);
*/

/*
$aUrls = array(
    $baseUrl . 'handel/discounter.html',
    $baseUrl . 'handel/biomaerkte.html',
    $baseUrl . 'handel/baeckereien.html',
    $baseUrl . 'handel/metzger.html',
    $baseUrl . 'handel/feinkost.html',
    $baseUrl . 'handel/getraenkemaerkte.html',
    $baseUrl . 'handel/weinhandel.html',
    $baseUrl . 'handel/tabakhaendler.html',
    $baseUrl . 'handel/confiserie.html',
    $baseUrl . 'handel/modehaeuser.html',
    $baseUrl . 'handel/schuhlaeden.html',
    $baseUrl . 'handel/sportgeschaefte.html',
    $baseUrl . 'handel/parfuemerien.html',
    $baseUrl . 'handel/juwelier.html',
    $baseUrl . 'handel/technikmarkt.html',
    $baseUrl . 'handel/reifenhaendler.html',
    $baseUrl . 'handel/kaufhaeuser.html',
    $baseUrl . 'handel/sonderposten.html',
    $baseUrl . 'handel/buchladen.html',
    $baseUrl . 'handel/einrichtungshaeuser.html',
    $baseUrl . 'handel/geschenkartikelladen.html',
    $baseUrl . 'handel/teppichmaerkte.html',
    $baseUrl . 'handel/gartenmarkt.html',
    $baseUrl . 'handel/baumaerkte.html',
    $baseUrl . 'handel/optiker.html',
    $baseUrl . 'handel/sanitaetshaeuser.html'
);
*/

$aUrls = array(
    $baseUrl . 'handel/baumaerkte.html'
);

$sPage = new Marktjagd_Service_Input_Page();
$aCompany = array();
foreach ($aUrls as $url) {
    $sPage->open($url);
    $page = $sPage->getPage()->getResponseBody();

    // Anzahl der Seiten ermitteln
    if (preg_match('#Seite: 1\|.+?>([0-9]+)</a>[^<]*</b>#is', $page, $match)){
        $pages = $match[1];
    } else {
        $pages = 1;
    }
            
    for ($pageCount = 0; $pageCount<$pages; $pageCount++){
        if ($pageCount == 0){
            $pageUrl = $url;
        } else {
            $pageUrl = preg_replace('#\.html$#', '_' . $pageCount . '.html', $url);
        }
        
        $sPage->open($pageUrl);
        echo "Seite: $pageUrl\n";
        echo "Seite $pageCount von $pages\n";
        $page = $sPage->getPage()->getResponseBody();
            
        if (preg_match_all('#<div[^>]*class="tab-f2"[^>]*>[^<]*<a[^>]*href="([^>]+)"[^>]*class="davis"#', $page, $match)){
            foreach ($match[1] as $idx => $companyLink){
                echo $companyLink . "\n";
                
                $sPage->open($baseUrl . $companyLink);
                $page = $sPage->getPage()->getResponseBody();
                
                $companyData = array();
                
                if (preg_match('#<h1>(.+?)</h1>#', $page, $titleMatch)){
                    $companyData[] = trim(strip_tags($titleMatch[1]));
                } else {
                    continue;
                }
                
                if (preg_match('#Fakten</h5>(.+?)</td>#', $page, $infoMatch)){
                    if (preg_match('#Standorte:\s*(.+?)<br[^>]*>#', $infoMatch[1], $storeCountMatch)){                        
                        $companyData[] = $storeCountMatch[1];                       
                    } else {
                        $companyData[] = 'ubekannt';
                    }
                    
                } else {
                    echo "ERROR. cannot get info box from: $companyLink\n";  
                    continue;
                }                                
                        
                $aCompany[] = $companyData;
                
                //Zend_Debug::dump($companyData);
            }
        }        
    }
}

$fw = fopen("./werzuwem_company.txt", "w");
echo "Schreibe Daten in werzuwem_company.txt\n";

foreach ($aCompany as $company) {
    fwrite($fw, implode(';', $company) . "\n");
}

fclose($fw);