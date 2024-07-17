<?php

/**
 * Brochure Crawler für Edeka Hieber (ID: 67783)
 */
class Crawler_Company_EdekaHieber_Brochure extends Crawler_Generic_Company
{
    private const FTP_FOLDER = 71668;
    private const REGEX_BROCHURE_FILE = '#KW\d{2}_(\d{6})_(\d{6})_SUEDWEST_(?:\d+_)?Hieber\.pdf#i';
    private const WEEK = 'next';
    private const DATE_FORMAT = 'd.m.Y H:i:s';

    private string $weekNr;

    public function crawl($companyId)
    {
        $this->weekNr = date('W', strtotime(self::WEEK . ' tuesday'));

        $brochureList = $this->getBrochuresFromFTP();

        if (empty($brochureList)) {
            throw new Exception('Company ID: ' . $companyId . ': no brochures found on FTP for KW' . $this->weekNr);
        }

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochureList as $brochureInfo) {
            $brochureData = $this->getBrochureData($brochureInfo);
            $brochure = $this->createBrochure($brochureData);
            $brochures->addElement($brochure);
        }

        return $this->getResponse($brochures, $companyId);
    }

    private function getBrochuresFromFTP(): array
    {
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localPath = $ftp->connect(self::FTP_FOLDER, TRUE);

        $brochures = [];
        foreach ($ftp->listFiles() as $ftpFolder) {
            if (!preg_match('#KW' . $this->weekNr . '#', $ftpFolder)) {
                continue;
            }

            foreach ($ftp->listFiles($ftpFolder) as $ftpFile) {
                if (!preg_match(self::REGEX_BROCHURE_FILE, $ftpFile, $validityMatch)) {
                    continue;
                }

                if (!preg_match('#KW' . $this->weekNr . '#', $ftpFile)) {
                    continue;
                }

                $brochures[] = [
                    'filePath' => $ftp->downloadFtpToDir($ftpFile, $localPath),
                    'visibilityStart' => preg_replace('#(\d{2})(\d{2})(\d{2})#', '$1.$2.20$3', $validityMatch[1]),
                    'end' => preg_replace('#(\d{2})(\d{2})(\d{2})#', '$1.$2.20$3', $validityMatch[2])
                ];
            }
        }
        $ftp->close();

        return $brochures;
    }

    private function getBrochureData(array $brochureInfo): array
    {
        $visibilityStart = $brochureInfo['visibilityStart'];
        if (isset($variant['visibilityStartOverride'])) {
            $visibilityStart = date(self::DATE_FORMAT, strtotime($visibilityStart . ' ' . $variant['visibilityStartOverride']));
        }

        return [
            'url' => $brochureInfo['filePath'],
            'title' => 'Hieber\'s Frischecenter: Meine Woche',
            'start' => date(self::DATE_FORMAT, strtotime($brochureInfo['visibilityStart'] . ' + 1 day')),
            'end' => $brochureInfo['end'],
            'visibilityStart' => $visibilityStart,
            'number' => 'KW' . $this->weekNr . '_' . date('Y', strtotime($brochureInfo['visibilityStart'] . ' + 1 day'))
        ];
    }

    protected function createBrochure(array $data): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setTitle($data['title'])
            ->setUrl($data['url'])
            ->setStart($data['start'])
            ->setEnd($data['end'])
            ->setVisibleStart($data['visibilityStart'])
            ->setBrochureNumber($data['number'])
            ->setVariety('leaflet');
    }
}
