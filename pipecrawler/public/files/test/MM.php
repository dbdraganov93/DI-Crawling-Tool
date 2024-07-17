<?php
try {
	$cacheFilename = '/tmp/fristo-cache';
	if (file_exists($cacheFilename)) {
		$html = file_get_contents($cacheFilename);
	} else {
		$html = file_get_contents('http://www.fristo.de/maerkte/sortiert-von-a-z/');
		file_put_contents($cacheFilename, $html);
	}

	$html = str_replace("\n", " ", $html);	
	if (!preg_match_all('#<div\s+class="plz">(?<zipcode>[0-9]+)</div>\s*<div class="ort">\s*<strong>(?<city>[^<]+)</strong><br\s*/>(?<street>[^<]+)</div>(\s*<div class="tel">(?<phone>[^<]+))?#', $html, $matches)) throw new Exception("unable to match any stores");

	function decodeCell($cell) {
		return trim(html_entity_decode($cell));
	}

	$lines = array();
	for ($i=0; $i<count($matches[1]); $i++) {
		$lines[] = array(
			'city'   => decodeCell($matches['city'][$i]),
			'zipcode'=> decodeCell($matches['zipcode'][$i]),
			'street' => decodeCell($matches['street'][$i]),
			'phone'  => decodeCell($matches['phone'][$i]),
		);
	}

	$fp = fopen('/dev/stdout','wt');
	fputcsv($fp, array_keys($lines[0]));
	foreach ($lines as $line) fputcsv($fp, $line);

} catch(Exception $e) {
	echo "OH NOES: {$t->message}\n";
}

