<?php
/**
 * Brochure Crawler for Opti Wohnwelt (ID: 71347) and Opti Megastore (ID: 81426)
 */

class Crawler_Company_Opti_Brochure extends Crawler_Generic_Company
{
    private const DEFAULT_COMPANY = 71347;
    private const REGEX_STORE_ASSIGNMENTS_FILE = '#Laufzeitenliste#';
    private const REGEX_ZIPCODES_FILE = '#PLZ_Werbegebiete\.xlsx#';

    private Marktjagd_Service_Input_PhpSpreadsheet $spreadsheetService;
    private Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    private int $companyId;
    private string $localPath;

    public function __construct()
    {
        parent::__construct();

        $this->spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
    }

    public function crawl($companyId)
    {
        $this->companyId = $companyId;

        $api = new Marktjagd_Service_Input_MarktjagdApi();
        $brochures = new Marktjagd_Collection_Api_Brochure();

        $ftpFiles = $this->downloadFilesFromFTP();

        $stores = $api->findStoresByCompany($companyId)->getElements();

        $brochuresData = $this->getBrochuresData($ftpFiles, $stores);
        foreach ($brochuresData as $brochureVariantsData) {
            foreach ($brochureVariantsData as $brochureData) {
                $brochure = $this->createBrochure($brochureData);
                $brochures->addElement($brochure);
            }
        }

        return $this->getResponse($brochures, $companyId);
    }

    private function downloadFilesFromFTP(): array
    {
        $storeAssignmentsFiles = [];
        $zipcodesFile = '';
        $brochureFiles = [];

        $this->localPath = $this->ftp->connect(self::DEFAULT_COMPANY, true);

        foreach ($this->ftp->listFiles() as $ftpFile) {
            if (preg_match(self::REGEX_STORE_ASSIGNMENTS_FILE, $ftpFile)) {
                $storeAssignmentsFiles[] = $this->ftp->downloadFtpToDir($ftpFile, $this->localPath);
                continue;
            }
            if (preg_match(self::REGEX_ZIPCODES_FILE, $ftpFile)) {
                $zipcodesFile = $this->ftp->downloadFtpToDir($ftpFile, $this->localPath);
                continue;
            }
            if (preg_match('#(.*)\.pdf$#', $ftpFile, $brochureNameMatch)) {
                $brochureFiles[$brochureNameMatch[1]] = $ftpFile;
            }
        }

        if (empty($storeAssignmentsFiles)) {
            throw new Exception('Company ID: ' . $this->companyId . ': No store assignments file was found on our ftp server.');
        }
        if (empty($zipcodesFile)) {
            throw new Exception('Company ID: ' . $this->companyId . ': No zipcodes file was found on our ftp server.');
        }

        return [
            'storeAssignmentFiles' => $storeAssignmentsFiles,
            'zipcodesFile' => $zipcodesFile,
            'brochureFiles' => $brochureFiles,
        ];
    }

    private function getBrochuresData(array $ftpFiles, array $stores): array
    {
        $zipcodesPerStore = $this->getZipcodesPerStore($ftpFiles['zipcodesFile']);

        $brochuresData = [];
        foreach ($ftpFiles['storeAssignmentFiles'] as $storeAssignmentsFile) {
            $storeAssignments = $this->spreadsheetService->readFile($storeAssignmentsFile)->getElement(0)->getData();
            foreach ($storeAssignments as $assignmentData) {
                $brochureName = $assignmentData[4];
                if (empty($brochureName)) {
                    continue;
                }

                $storeNumber = $assignmentData[1];
                if ('Filiale' === $storeNumber) {
                    continue;
                }
                if (!isset($stores[$storeNumber])) {
                    $this->_logger->err('Company ID: ' . $this->companyId . ': Store not found: ' . $storeNumber);
                    continue;
                }

                if (empty($ftpFiles['brochureFiles'][$brochureName])) {
                    $this->_logger->err('Company ID: ' . $this->companyId . ': Brochure file not found on our ftp server: ' . $brochureName);
                    continue;
                }
                
                $zipcodes = $zipcodesPerStore[$storeNumber];
                if (!empty($brochuresData[$brochureName][$assignmentData[2]]['zipcodes'])) {
                    $zipcodes = array_merge($zipcodes, $brochuresData[$brochureName][$assignmentData[2]]['zipcodes']);
                    $zipcodes = array_unique($zipcodes);
                }
                
                if (empty($brochuresData[$brochureName][$assignmentData[2]]['number'])) {
                    $index = $brochuresData[$brochureName] ? count($brochuresData[$brochureName]) : 0;
                    $brochuresData[$brochureName][$assignmentData[2]]['number'] = $this->generateBrochureNumber($brochureName, $index);
                }
                
                $brochuresData[$brochureName][$assignmentData[2]]['stores'][] = $storeNumber;
                $brochuresData[$brochureName][$assignmentData[2]]['from'] = $assignmentData[2];
                $brochuresData[$brochureName][$assignmentData[2]]['to'] = $assignmentData[3];
                $brochuresData[$brochureName][$assignmentData[2]]['title'] = $assignmentData[5];
                $brochuresData[$brochureName][$assignmentData[2]]['zipcodes'] = $zipcodes;

                if (empty($brochuresData[$brochureName][$assignmentData[2]]['url'])) {
                    $brochureUrl = $this->ftp->downloadFtpToDir($ftpFiles['brochureFiles'][$brochureName], $this->localPath . $assignmentData[2] . '_');
                    if (!$brochureUrl) {
                        $this->_logger->err('Company ID: ' . $this->companyId . ': Can\'t download the brochure: ' . $brochureName);
                        continue;
                    }

                    $brochuresData[$brochureName][$assignmentData[2]]['url'] = $brochureUrl;
                }
            }
        }

        return $brochuresData;
    }

    private function getZipcodesPerStore(string $zipcodesFile): array
    {
        $zipcodesPerStore = [];

        $zipcodesFileData = $this->spreadsheetService->readFile($zipcodesFile)->getElements();
        foreach ($zipcodesFileData as $spreadsheetTab) {
            $zipcodes = $this->parseZipcodesData($spreadsheetTab->getData());

            $storeNumbers = explode('_', $spreadsheetTab->getTitle());
            foreach ($storeNumbers as $storeNumber) {
                $zipcodesPerStore[$storeNumber] = $zipcodes;
            }
        }

        return $zipcodesPerStore;
    }

    private function parseZipcodesData(array $zipcodesData): array
    {
        $zipcodes = array_map(function ($row) {
            return trim($row[1]);
        }, $zipcodesData);
        $zipcodes = array_filter($zipcodes, function ($row) {
            return !empty($row) && is_numeric($row);
        });

        return $zipcodes;
    }

    private function generateBrochureNumber(string $brochureFileName, int $index = 0): string
    {
        $suffix = '';
        if (0 < $index) {
            $suffix = '_' . $index;
        }

        return $brochureFileName . $suffix;
    }

    private function createBrochure(array $data): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setUrl($data['url'])
            ->setStart($data['from'])
            ->setEnd($data['to'])
            ->setVisibleStart($data['from'])
            ->setBrochureNumber($data['number'])
            ->setTitle($data['title'])
            ->setZipCode(implode(',', $data['zipcodes']))
            ->setStoreNumber(implode(',', $data['stores']))
            ->setVariety('leaflet');
    }
}
