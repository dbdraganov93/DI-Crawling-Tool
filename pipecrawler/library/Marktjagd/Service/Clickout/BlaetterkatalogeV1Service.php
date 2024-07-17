<?php

class Marktjagd_Service_Clickout_BlaetterkatalogeV1Service implements Marktjagd_Service_Clickout_ClickoutInterface
{
    private const KATALOG_URL = 'https://blaetterkataloge.lagerhaus.at/frontend/mvc/api/catalogs/';

    /**
     * @throws Exception
     */
    public function addClickout(string $pdf, string $url, string $localPath): string
    {
        $content = file_get_contents($url);
        if (!preg_match('/catalogId=(.*)"/', $content, $match)) {
            throw new Exception('Domain name is not found');
        }

        $brochureNumber = end($match);

        $pdfService = new Marktjagd_Service_Output_Pdf();
        $xmlString = file_get_contents(self::KATALOG_URL . $brochureNumber . '/v1/xml/catalog.xml');

        $pattern = '#<detaillevel[^>]*name="large"[^>]*width="([^"]+?)"[^>]*height="([^"]+?)"#';
        if (preg_match($pattern, $xmlString, $dimensionMatch)) {
            $pageWidth = $dimensionMatch[1];
            $pageHeight = $dimensionMatch[2];
        }

        $xmlData = new SimpleXMLElement($xmlString);
        $pageNumber = reset($xmlData->attributes()->nofpages);

        for ($page = 1; $page <= $pageNumber; $page++) {
            $xmlString = file_get_contents(self::KATALOG_URL . $brochureNumber . '/v1/maps/bk_' . $page . '.xml');
            $xmlData = new SimpleXMLElement($xmlString);
            $linksAdded = [];

            foreach ($xmlData->area as $singleLink) {
                $aCoords = preg_split('#\s*,\s*#', (string)$singleLink->attributes()->coords);
                $endX = min($aCoords[2], 1503);
                $endY = max($pageHeight - $aCoords[3], 0);

                $url = reset($singleLink->art_url->attributes()->value);
                if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
                    continue;
                }

                if (in_array($url, $linksAdded)) {
                    continue;
                }
                $linksAdded[] = $url;

                $aCoordsToLink[] = [
                    'page' => $page - 1,
                    'height' => $pageHeight,
                    'width' => $pageWidth,
                    'startX' => $aCoords[0] + 45.0,
                    'endX' => $endX + 45.0,
                    'startY' => $pageHeight - $aCoords[1] + 45.0,
                    'endY' => $endY + 45.0,
                    'link' => $url,
                ];
            }
        }

        $coordFileName = $localPath . 'coordinates_' . $brochureNumber . '_' . '.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aCoordsToLink));
        fclose($fh);

        return $pdfService->setAnnotations($pdf, $coordFileName);
    }
}