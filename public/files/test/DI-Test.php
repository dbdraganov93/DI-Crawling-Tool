<?php
$url = 'http://www.fristo.de/maerkte/sortiert-von-a-z/';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
$result = curl_exec($ch);
curl_close($ch);
$pattern = '#<div[^>]*class="row"[^>]*>(.+?)<div[^>]*class="anfahrt"#s';
if (!preg_match_all($pattern, $result, $storeMatches)) {
    throw new Exception('no stores found.');
}
$filePath = '/home/frank.wehder/crawler/public/files/test/stores.csv';
$fh = fopen($filePath, 'w+');
fputcsv($fh, array('zip', 'city', 'street', 'phone'), ';');
foreach ($storeMatches[1] as $singleStore) {
    $pattern = '#div[^>]*class="(plz|ort|tel)"[^>]*>(\s*<[^>]*>\s*)*\s*(.+?)\s*<\/div#s';
    if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
        throw new Exception('invalid pattern.');
    }
    $aInfos = array_combine($infoMatches[1], $infoMatches[3]);
    $aAddress = preg_split('#(\s*<[^>]*>\s*)+#', $aInfos['ort']);
    fputcsv($fh, array($aInfos['plz'], $aAddress[0], $aAddress[1], $aInfos['tel']), ';');
}
fclose($fh);

echo $filePath . "\n";