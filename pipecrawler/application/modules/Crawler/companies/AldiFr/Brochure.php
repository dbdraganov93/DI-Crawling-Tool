<?php

/**
 * Brochure Crawler for Aldi France (ID: 73615)
 */
class Crawler_Company_AldiFr_Brochure extends Crawler_Generic_Company
{
    private const BASE_URL = 'https://www.aldi.fr/';
    private const DOWNLOAD_URL_BASE = 'https://catalogues.aldi.fr/';
    private const WEEK = 'next';

    private string $year;
    private string $weekNr;

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();

        $catalogueUrl = self::BASE_URL . 'catalogues.html';

        $sPage->open($catalogueUrl);
        $page = $sPage->getPage()->getResponseBody();

        $this->weekNr = date('W', strtotime(self::WEEK . ' week'));
        $this->year = date('Y', strtotime($this->weekNr . ' week'));

        // check if the brochure exists on the website
        $pattern = '#<a[^>]+href="[^"]*(catalogues/[^"]+'.$this->weekNr.'[^"]*)"#';
        if (!preg_match_all($pattern, $page, $brochureUrlMatches)) {
            throw new Exception($companyId . ': unable to get any brochures.');
        }

        $validityDates = $this->getStartAndEndDate();

        $brochureUrl = $this->getBrochureUrl($companyId);
        if (!$this->isReadable($brochureUrl)) {
            throw new Exception('file: ' . $brochureUrl . ' is not readable!');
        }

        $brochureData = [
            'url' => $brochureUrl,
            'start' => $validityDates['start'],
            'end' => $validityDates['end']
        ];
        $eBrochure = $this->generateBrochure($brochureData);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }

    private function getStartAndEndDate(): array
    {
        $dates['start'] = date('Y-m-d', strtotime('tuesday ' . self::WEEK . ' week'));
        $dates['end'] = date('Y-m-d', strtotime('tuesday ' . self::WEEK . ' week + 6 days'));
        return $dates;
    }

    private function getBrochureUrl(int $companyId): string
    {
        $sHttp = new Marktjagd_Service_Transfer_Http();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);

        return $localPath . md5($this->weekNr . '_' . $this->year) . '.pdf';
    }

    private function isReadable(string $path): bool
    {
        $brochureBaseUrl = self::DOWNLOAD_URL_BASE . 'kw' . $this->weekNr . $this->year . '/GetPDF.ashx';

        $fh = fopen($path, 'w+');
        $ch = curl_init($brochureBaseUrl);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_FILE, $fh);
        $brochureFile = curl_exec($ch);
        curl_close($ch);

        $isReadable = fwrite($fh, $brochureFile);
        fclose($fh);

        return $isReadable !== false;
    }

    private function generateBrochure(array $data): Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Aldi Süd: Wochenangebote')
            ->setUrl($data['url'])
            ->setStart($data['start'])
            ->setEnd($data['end'])
            ->setBrochureNumber('KW' . $this->weekNr . '_' . $this->year . '_FR')
            ->setVariety('leaflet');

        return $eBrochure;
    }
}