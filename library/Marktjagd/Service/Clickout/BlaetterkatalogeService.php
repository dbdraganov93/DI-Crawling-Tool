<?php

class Marktjagd_Service_Clickout_BlaetterkatalogeService implements Marktjagd_Service_Clickout_ClickoutInterface
{
    private const KATALOG_URL = 'https://blaetterkatalog.lagerhaus.at/KATALOGE/Lagerhaus/Garten-Freizeit/';

    /**
     * @throws Exception
     */
    public function addClickout(string $pdf, string $url, string $localPath): string
    {
        $pattern = '#catalog=3D(.*)&amp;domain=3D(.*)#';
        if (!preg_match($pattern, $url, $categoryMatch)) {
            throw new Exception('Domain name is not found');
        }

        $brochureNumber = $categoryMatch[1];

        $xmlString = file_get_contents(self::KATALOG_URL . $brochureNumber . '/xml/catalog.xml');

        $pattern = '#<detaillevel[^>]*name="large"[^>]*width="([^"]+?)"[^>]*height="([^"]+?)"#';
        if (preg_match($pattern, $xmlString, $dimensionMatch)) {
            $pageWidth = $dimensionMatch[1];
            $pageHeight = $dimensionMatch[2];
        }

        $xmlData = new SimpleXMLElement($xmlString);
        $pageNumber = reset($xmlData->attributes()->nofpages);

        for ($page = 1; $page <= $pageNumber; $page++) {
            $xmlString = file_get_contents(self::KATALOG_URL . $brochureNumber . '/maps/bk_' . $page . '.xml');
            $xmlData = new SimpleXMLElement($xmlString);

            foreach ($xmlData->area as $singleLink) {
                $aCoords = preg_split('#\s*,\s*#', (string)$singleLink->attributes()->coords);
                $endX = min($aCoords[2], 1503);
                $endY = max($pageHeight - $aCoords[3], 0);

                $url = reset($singleLink->children()->url->attributes()->value);

                if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
                    continue;
                }

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

        return $this->pdfService->setAnnotations($pdf, $coordFileName);
    }
}
