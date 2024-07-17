<?php

require_once APPLICATION_PATH . '/../vendor/autoload.php';
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

use phpseclib\Net\SFTP;

/**
 * Discover Brochure-Crawler für Medimax (ID: 101)
 * MEDIMAX EM Highlights
 */

class Crawler_Company_Medimax_DiscoverBrochure extends Crawler_Generic_Company
{
    function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_Ftp();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $folder = 'mmde';
        $host = 'filecenter.prod.team-ec.com';
        $username = 'offerista';
        $password = 'Cn2zYuBq4Vis';

        $sftp = new SFTP($host);
        $sftp->login($username, $password);

        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $localAssignmentFiles = [];
        $nextCw = date('W', strtotime('next week'));
        $currentCw = date('W', strtotime('this week'));
        $remoteFiles = $sftp->nlist($folder);
        foreach ($remoteFiles as $remoteFile) {
            $patternCurrentCw = "#kw$currentCw\.csv#i";
            $patternNextCw = "#kw$nextCw\.csv#i";
            if (preg_match($patternCurrentCw, $remoteFile) or preg_match($patternNextCw, $remoteFile)) {
                $assignmentFilePath = $folder . '/' . $remoteFile;
                $localAssignmentFile = $localPath . $remoteFile;
                if (!$sftp->get($assignmentFilePath, $localAssignmentFile)) {
                    throw new Exception('error during file download: ' . $remoteFile);
                }
                $localAssignmentFiles[str_replace('.csv', '', $remoteFile)] = $localAssignmentFile;
            }
        }

        if (empty($localAssignmentFiles)) {
            throw new Exception('no assignment file available');
        }

        foreach ($localAssignmentFiles as $localAssignmentFile) {
            $this->_logger->info("found assignment file: $localAssignmentFile");
        }

        /*
         * We used this combination to fix encoding issues back when the assignment file was ISO-8859-1 encoded
         * exec("iconv -f 'ISO-8859-1' -t 'UTF-8' $localAssignmentFile > " . $localPath . 'converted' . $remoteFile);
         * $localAssignmentFile = $localPath . 'converted' . $remoteFile;
         */

        $this->_logger->info('requesting active api articles');
        $aApiData = $sApi->getActiveArticleCollection($companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        $activeApiArticles = count($aArticleIds);
        $this->_logger->info($activeApiArticles . ' active articles available via API');

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($localAssignmentFiles as $campaign => $localAssignmentFile) {
            $products = [];
            $validityStart = null;
            $validityEnd = null;
            $stores = null;
            $brochureNumber = null;
            $header = null;
            $brochureTitle = null;
            $headerOver = false;
            $handle = fopen($localAssignmentFile, "r");
            if ($handle) {
                $missingArticles = 0;
                $linesInAssignmentFile = 0;

                while (($line = fgets($handle)) !== false) {
                    $explodedLine = explode(';', $line);
                    // skipping empty lines in assignment csv
                    if (empty($explodedLine[0])) {
                        continue;
                    }

                    if ($headerOver) {
                        $linesInAssignmentFile++;
                        $articleCodePlusCampaign = $explodedLine[0] . '-' . $campaign;
                        if ($aArticleIds[$articleCodePlusCampaign]) {
                            $products[$explodedLine[1]][] = [
                                'article_id' => $aArticleIds[$articleCodePlusCampaign],
                                'category' => $explodedLine[1]
                            ];
                        } else {
                            $this->_logger->warn("The following article from the assignment file: $campaign was not returned by our API: " . $articleCodePlusCampaign);
                            $missingArticles++;
                        }
                    } else {
                        if (preg_match('#ltig von#', $explodedLine[0])) {
                            $validityStart = $explodedLine[1];
                        } elseif (preg_match('#ltig bis#', $explodedLine[0])) {
                            $validityEnd = $explodedLine[1];
                        } elseif (preg_match('#Bezeichnung#', $explodedLine[0])) {
                            $brochureTitle = $explodedLine[1];
                        } elseif (preg_match('#Kampagne#', $explodedLine[0])) {
                            $brochureNumber = $explodedLine[1];
                        } elseif (preg_match('#Headergrafik#', $explodedLine[0])) {
                            $header = $explodedLine[1];
                        } elseif (preg_match('#Standorte#', $explodedLine[0])) {
                            $stores = $explodedLine[1];
                        } elseif (preg_match('#Artikelnummer#', $explodedLine[0])) {
                            $headerOver = true;
                        }
                    }
                }
                fclose($handle);
            } else {
                $this->_logger->err('localAssignmentFile was not readable: SKIPPING ' . $campaign);
                continue;
            }

            if ($validityStart == null or $validityEnd == null or $brochureTitle == null or $brochureNumber == null or $header == null or $stores == null) {
                $this->_logger->err('parsing of header in assignment file failed');
                continue;
            }

            $this->_logger->info('found: ' . ($linesInAssignmentFile - $missingArticles) . " active product(s) from assignment file: $campaign via api");
            $this->_logger->warn($missingArticles . " product(s) from assignment file: $campaign were missing");

            $threshold = 0.25;
            if (($missingArticles / $linesInAssignmentFile) > $threshold) {
                $this->_logger->err('more than ' . ($threshold * 100) . '% product "artikelcodes" from the assignment file were not returned from our API: SKIPPING ' . $campaign);
                continue;
            }

            $discover = [];
            foreach ($products as $category => $productCollection) {
                $discover[] = [
                    'page_metaphore' => $category,
                    'products' => array_map(function($v) {
                        return [
                            'product_id' => (int) $v['article_id'],
                            'priority' => rand(1, 3)
                        ];
                    }, $productCollection)
                ];
            }

            $this->_logger->info('requesting discover layout');

            $response = Blender::blendApi($companyId, $discover, $brochureNumber);
            if ($response['http_code'] != 200) {
                $this->_logger->err($response['error_message']);
                throw new Exception('blender api did not work out');
            }

            $strLayout = $response['body'];
            $this->_logger->info('downloading and transforming header image');
            $localHeaderFile = $sHttp->getRemoteFile($header, $localPath);
            $pdfFromImageArray = $sPdf->getPdfFromImageArray([$localHeaderFile], $localPath);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle($brochureTitle)
                ->setBrochureNumber($brochureNumber)
                ->setUrl($pdfFromImageArray)
                ->setVariety('leaflet')
                ->setStart($validityStart)
                ->setEnd($validityEnd)
                ->setVisibleStart($validityStart)
                ->setStoreNumber($stores)
                ->setLayout($strLayout);

            $cBrochures->addElement($eBrochure);
        }
        return $this->getResponse($cBrochures, $companyId);
    }
}
