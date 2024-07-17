<?php
/**
 * Brochure Crawler für Akzenta (ID: 71922)
 */

class Crawler_Company_Akzenta_Brochure extends Crawler_Generic_Company
{
    private const WEEK = 'next';
    private const DATE_FORMAT = 'd.m.Y';
    private const BROCHURE_DATA_MAP = [
        'DO' => [
            'store' => 'id:1713239',
            'title' => 'akzenta: Dortmund',
        ],
        'HLH' => [
            'store' => 'id:1587827',
            'title' => 'akzenta: Heiligenhaus',
        ]
    ];

    private string $weekNr;
    private string $weekYear;
    private string $companyId;

    public function crawl($companyId)
    {
        $timeService = new Marktjagd_Service_Text_Times();
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $this->companyId = $companyId;
        $this->weekNr = $timeService->getWeekNr(self::WEEK);
        $this->weekYear = $timeService->getWeeksYear(self::WEEK);

        //generate local machine path
        $localPath = $ftp->generateLocalDownloadFolder($companyId);

       //add brochure to the local machine path
        $brochureList = $this->getFilesFromNextCloud($localPath);
        if (empty($brochureList)) {
            throw new Exception('Company ID: ' . $companyId . ': did not find any brochures on NextCloud!');
        }

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochureList as $brochureName => $brochurePath) {
            $this->_logger->info('Found brochure: ' . $brochureName);
            $brochure = $this->createBrochure($brochurePath, $brochureName);
            $brochures->addElement($brochure);
        }

        return $this->getResponse($brochures, $companyId);
    }

    private function getFilesFromNextCloud(string $localPath): array
    {
        $resultFiles = [];

        # set up the config to the MC NextCloud
        $configIni = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', 'production');
        $username = $configIni->nextcloud->mc->username;
        $password = $configIni->nextcloud->mc->password;

        $weekYearString = $this->weekNr . '_' . $this->weekYear;

        foreach (self::BROCHURE_DATA_MAP as $distribution => $storeNumber) {
            $url =  'https://nextcloud.media-central.com/nextcloud/remote.php/dav/files/svc.crawler/Offerista%20(2)/akzenta/KW' . $weekYearString
                . '/' . 'akzenta_HZ_KW' . $weekYearString . '_A4_RZ_' . $distribution . '_digi.pdf';

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => 'UTF-8',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_USERNAME => $username,
                CURLOPT_PASSWORD => $password,
                CURLOPT_CUSTOMREQUEST => 'PROPFIND',
                CURLOPT_POSTFIELDS => '<?xml version="1.0" encoding="UTF-8"?>
                 <d:propfind xmlns:d="DAV:">-->
                   <d:prop xmlns:oc="http://owncloud.org/ns">-->
                     <d:getlastmodified/>-->
                   </d:prop>-->
                 </d:propfind>',
            ]);

            $response = curl_exec($curl);
            curl_close($curl);

            preg_match_all('#<d:href>(?<files>[^<]+?\.pdf)<\/d:href>#',$response, $matches);

            foreach($matches['files'] as $remoteFile) {

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://nextcloud.media-central.com' . $remoteFile,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => 'UTF-8',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_USERNAME => $username,
                    CURLOPT_PASSWORD => $password,
                ));

                $response = curl_exec($curl);
                curl_close($curl);

                file_put_contents($localPath . basename($remoteFile), $response);

                $resultFiles[basename($remoteFile)] = $localPath . basename($remoteFile);
            }
        }

        return $resultFiles;
    }

    private function createBrochure(string $brochurePath, string $brochureName): Marktjagd_Entity_Api_Brochure
    {
        if (empty($brochurePath)) {
            throw new Exception('did not find brochure file: ' . $brochurePath);
        }

        if (!preg_match('#(DO|HLH)_digi#', $brochureName, $matches)) {
            throw new Exception('Company ID: ' . $this->companyId . ': could not find brochure distribution in file name: ' . $brochurePath);
        }

        $brochurePrefix = $matches[1];
        $brochureData = self::BROCHURE_DATA_MAP[$brochurePrefix];

        $brochure = new Marktjagd_Entity_Api_Brochure();
        return $brochure->setTitle($brochureData['title'])
            ->setBrochureNumber($brochurePrefix . '-KW' . $this->weekNr . '-' . $this->weekYear )
            ->setUrl($this->setClickouts($brochurePath))
            ->setStoreNumber($brochureData['store'])
            ->setStart(date(self::DATE_FORMAT, strtotime(self::WEEK . ' week monday')))
            ->setEnd(date(self::DATE_FORMAT, strtotime(self::WEEK . ' week saturday')))
            ->setVisibleStart(date(self::DATE_FORMAT, strtotime($brochure->getStart() . ' -1 day')));
    }

    private function setClickouts(string $brochurePath): string
    {
        $pdfService = new Marktjagd_Service_Output_Pdf();
        $pageCount = $pdfService->getPageCount($brochurePath);

        for($i = 0; $i < $pageCount; $i++) {
            // Clickout on first age
            $clickouts[] = [
                'page' => $i,
                'link' => 'https://rundum-akzenta.de/angebote/',
                'startX' => '340',
                'endX' => '390',
                'startY' => '740',
                'endY' => '790'
            ];
        }

        // clickout coordinates
        $coordFileName = APPLICATION_PATH . '/../public/files/coordinates_' . $this->companyId . '.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($clickouts));
        fclose($fh);

        return $pdfService->setAnnotations($brochurePath, $coordFileName);
    }
}
