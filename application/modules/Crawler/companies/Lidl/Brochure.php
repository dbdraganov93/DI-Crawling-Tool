<?php

/**
 * NewGen Brochure Crawler für Lidl (ID: 28)
 */

class Crawler_Company_Lidl_Brochure extends Crawler_Generic_Company
{
    private const REGEX_DISTRIBUTION_MAPPINGS_FILE = '#DE Filialen_20230607\.xlsx#';
    private const WEEK = 'next';

    private Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    private string $weekNr;
    private int $companyId;

    public function __construct()
    {
        parent::__construct();

        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
    }

    public function crawl($companyId)
    {
        $brochures = new Marktjagd_Collection_Api_Brochure();
        $timesService = new Marktjagd_Service_Text_Times();

        $this->companyId = $companyId;

        $downloadFolder = $this->ftp->connect($this->companyId, TRUE);

        $distributionMappings = $this->getDistributionMappings($downloadFolder);

        $this->weekNr = $timesService->getWeekNr(self::WEEK);

        $this->extractBrochuresFromZip($downloadFolder);

        $pdfFiles = $this->getAllPdfsRecurse($downloadFolder);
        if (!count($pdfFiles)) {
            throw new Exception('Company ID: ' . $this->companyId . ': no pdf-files on Offerista FTP for KW ' . $this->weekNr);
        }

        foreach ($pdfFiles as $pdf) {
            $distributions = [];
            if (preg_match('#(FHZ|NHZ|SIF|HHZ|SHZ)\_KW[0-9]{1,2}_(.+?)\_([0-9]{8})\_([0-9]{8})\_*.*?\.pdf#is', $pdf, $matchFile)) {
                $distributions = explode('_', $matchFile[2]);
            }

            $storeNumbers = $this->generateStoreNumbersString($distributions, $distributionMappings);
            if (!$storeNumbers) {
                continue;
            }

            $brochure = $this->generateBrochure($storeNumbers, $pdf);

            $brochures->addElement($brochure);
        }

        return $this->getResponse($brochures, $companyId);
    }

    /**
     * @throws Exception
     */
    private function extractBrochuresFromZip(string $downloadFolder): void
    {
        $archiveService = new Marktjagd_Service_Input_Archive();

        $zipFiles = $this->ftp->listFiles('.', '#KW\s*' . $this->weekNr . '\.zip#i');
        if (empty($zipFiles)) {
            // Check for file 'parts'
            $zipFiles = $this->ftp->listFiles('.', '#KW\s*' . $this->weekNr . '\s*Teil\s*\d{1}\.zip#i');
            if (empty($zipFiles)) {
                throw new Exception($this->companyId . ': no pdf-files on Offerista FTP for KW ' . $this->weekNr);
            }
        }

        foreach ($zipFiles as $zip) {
            $this->_logger->info($this->companyId . ': trying to download ' . $zip);
            $localZip = $this->ftp->downloadFtpToDir($zip, $downloadFolder);
            if (!strlen($localZip)) {
                $this->_logger->err($this->companyId . ': ' . $zip . ' failed to download!');
                continue;
            }
            $this->_logger->info($this->companyId . ': ' . $zip . ' downloaded successfully.');

            $archiveService->unzip($localZip, $downloadFolder);
        }
    }

    private function getAllPdfsRecurse(string $dir): array
    {
        $pdfs = [];
        foreach (scandir($dir) as $file) {
            if (0 == strpos($file, '.')) {
                continue;
            }

            $fullPath = $dir . '/' . $file;
            if (is_dir($fullPath)) {
                $pdfs = array_merge($pdfs, $this->getAllPdfsRecurse($fullPath));
            } else if (is_file($fullPath) && preg_match('/\.pdf$/i', $file)) {
                $pdfs[] = $fullPath;
            }
        }
        return $pdfs;
    }

    /**
     * @throws Exception
     */
    private function getDistributionMappings(string $downloadFolder): array
    {
        $excelService = new Marktjagd_Service_Input_PhpExcel();
        $api = new Marktjagd_Service_Input_MarktjagdApi();

        $storeMappingsFile = $this->ftp->listFiles('.', self::REGEX_DISTRIBUTION_MAPPINGS_FILE);
        if (!count($storeMappingsFile)) {
            throw new Exception('no xlsx-file for distribution mapping on Offerista FTP');
        }

        $storeMappingsFile = $this->ftp->downloadFtpToDir($storeMappingsFile[0], $downloadFolder);

        $distributions = [];
        $storeList = $excelService->readFile($storeMappingsFile, true)->getElement(0)->getData();
        foreach ($storeList as $storeData) {
            $distributions[$storeData['Fil.Nr.']] = $storeData['Regionalgesellschaft'];
        }

        $apiStores = $api->findStoresByCompany($this->companyId)->getElements();

        $distributionMappings = [];
        foreach ($apiStores as $storeNumber => $store) {
            if (isset($distributions[$storeNumber])) {
                $distributionMappings[$distributions[$storeNumber]][] = $storeNumber;
            }
        }

        return $distributionMappings;
    }

    protected function generateStoreNumbersString(array $distributions, array $distributionMappings): string
    {
        $stores = [];

        foreach ($distributions as $distribution) {
            if ('ECI' == $distribution) {
                $this->_logger->info($this->companyId . ': ECI distribution found, skipping');
                continue;
            }


            if (!isset($distributionMappings[$distribution])) {
                $this->_logger->info($this->companyId . ': ' . $distribution . ' distribution not found!');
                continue;
            }

            $stores = array_merge($stores, $distributionMappings[$distribution]);
        }

        return implode(',', $stores);
    }

    protected function generateBrochure(string $storeNumbers, string $fileName): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        if (preg_match('#(FHZ|NHZ|SIF|HHZ|SHZ)\_KW([0-9]{1,2})_(.+?)\_(([0-9]{4})([0-9]{2})([0-9]{2}))\_([0-9]{4})([0-9]{2})([0-9]{2})\_*.*?\.pdf#is', $fileName, $matchFile)) {
            $visibilityDate = $matchFile[7] . '.' . $matchFile[6] . '.' . $matchFile[5];
            $endDate = $matchFile[10] . '.' . $matchFile[9] . '.' . $matchFile[8];
            $startDate = date('d.m.Y', strtotime($visibilityDate . ' + 8 days'));

            $title = 'Lidl: Wochenangebote';
            $variety = 'leaflet';
            if ($matchFile[1] == 'NHZ') {
                $title = 'Lidl: Themen Spezial';
                $variety = 'customer_magazine';
            }

            $tempFilePath = APPLICATION_PATH . '/../public/files/tmp/';
            $tempFileName = $tempFilePath . $matchFile[3] . '.pdf';
            if (!copy($fileName, $tempFileName)) {
                $this->_logger->alert('Not able to create copy the Bring brochures clone!!! Please fix me!');
            }

            $brochure->setStoreNumber($storeNumbers)
                ->setTitle($title)
                ->setVariety($variety)
                ->setUrl($tempFileName)
                ->setStart($startDate)
                ->setVisibleStart(date('d.m.Y', strtotime($startDate . '- 2 days')))
                ->setEnd($endDate)
                ->setBrochureNumber($matchFile[1] . '_KW' . $matchFile[2] . date_format(date_create($visibilityDate), 'Y') . '_' . substr($matchFile[3], 0, 19));
        }

        return $brochure;
    }
}


