<?php

class Wgw_Service_Import_Import
{

    protected $_aCompaniesMapped;
    protected $_logger;

    public function __construct()
    {
        $this->_logger = Zend_Registry::get('logger');
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $localPath = $sFtp->connect('dataAt', TRUE);

        $localJMappingFile = $sFtp->downloadFtpToDir('wgwOgMapping.json', $localPath);

        $sFtp->close();

        $aMappingData = json_decode(file_get_contents($localJMappingFile), TRUE);

        foreach ($aMappingData as $singleData) {
            $this->_aCompaniesMapped[$singleData['company_id']] = $singleData['wgw_id'];
        }

    }

    public function import($eCrawlerConfig, $response)
    {
        $type = $eCrawlerConfig->getCrawlerType()->getType();
        $idCompany = $eCrawlerConfig->getCompany()->getIdCompany();
        $idCompanyWgw = $this->_aCompaniesMapped[$idCompany];

        if ($response->getIsImport()) {
            $configCrawler = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            if ($configCrawler->crawler->s3->active) {
                // For S3, the URL to the file is already in the response.
                $url = $response->getFileName();
            } else {
                $url = Marktjagd_Service_Output_File::generatePublicUrl($response->getFileName());
            }

            if (!$url) {
                throw new Exception('Es konnte keine öffentliche URL für die Datei ' . $response->getFileName()
                    . ' im ' . ucwords($type) . '-Crawler für'
                    . ' Unternehmen ' . $eCrawlerConfig->getCompany()->getName() . ' (ID: ' . $idCompany . ')'
                    . ' erzeugt werden!');
            }

            if (preg_match('#amazonaws#', $url)) {
                $sS3File = new Marktjagd_Service_Output_S3File('mjcsv', basename($url));
                if ($localPath = $sS3File->generateLocalDownloadFolder($eCrawlerConfig->getCompany()->getIdCompany())) {
                    $this->_logger->log($idCompany . ': folder created ' . $localPath, Zend_Log::INFO);
                }

                if ($localFilePath = $sS3File->getFileFromBucket($url, $localPath)) {
                    $this->_logger->log($idCompany . ': file downloaded ' . $url, Zend_Log::INFO);
                }
            }

            $sCsvConvert = new Marktjagd_Service_Input_MarktjagdCsv();
            $cElements = $sCsvConvert->convertToCollection($localFilePath, $type);

            $imported = FALSE;
            switch ($cElements) {
                case is_a($cElements, 'Marktjagd_Collection_Api_Store'):
                    {
                        $sWgwImportStores = new Wgw_Service_Import_StoreImport($idCompanyWgw);
                        $sWgwImportStores->putStores($cElements);
                        $imported = TRUE;
                        break;
                    }

                case is_a($cElements, 'Marktjagd_Collection_Api_Brochure'):
                case is_a($cElements, 'Marktjagd_Collection_Api_Article'):
                    {
                        $sWgwImportOffers = new Wgw_Service_Import_OfferImport($idCompanyWgw);
                        foreach ($cElements->getElements() as $eOffer) {
                            $sWgwImportOffers->putOffer($eOffer);
                        }
                        $imported = TRUE;
                        break;
                    }

                default:
                    {
                        throw new Exception($idCompany . ': not a valid OG format: ' . $localFilePath);
                    }
            }

            if (!$imported) {
                throw new Exception('Fehler beim Import des Unternehmens '
                    . $eCrawlerConfig->getCompany()->getName() . ' (ID:' . $idCompany . ') aufgetreten.');
                $response->setLoggingCode(Crawler_Generic_Response::IMPORT_FAILURE_ADD);
            }
        }

        return $response;
    }
}