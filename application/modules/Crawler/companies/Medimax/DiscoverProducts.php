<?php

require_once APPLICATION_PATH . '/../vendor/autoload.php';

use phpseclib\Net\SFTP;

/**
 * Discover Product-Crawler für Medimax (ID: 101)
 */

class Crawler_Company_Medimax_DiscoverProducts extends Crawler_Generic_Company
{
    function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_Ftp();

        $folder = 'mmde';
        $fileNameProductFeed = 'produkte.zip';
        $productFeedPath = $folder . '/' . $fileNameProductFeed;

        $host = 'filecenter.prod.team-ec.com';
        $username = 'offerista';
        $password = 'Cn2zYuBq4Vis';

        $sftp = new SFTP($host);
        $sftp->login($username, $password);

        if (!$sftp->file_exists($productFeedPath)) {
            throw new Exception('product feed not available');
        }

        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $localProductFeed = $localPath . '/' . $fileNameProductFeed;
        if (!$sftp->get($productFeedPath, $localProductFeed)) {
            throw new Exception('error during file download: ' . $productFeedPath);
        }

        $this->_logger->info('downloaded product archive');

        $zip = new ZipArchive;
        if ($zip->open($localProductFeed) === TRUE) {
            $zip->extractTo($localPath, 'produkte.json');
            $zip->close();
            $this->_logger->info('extracted product archive');
        } else {
            throw new Exception('error during product archive extraction');
        }

        $localProductFeed = $localPath . '/produkte.json';

        $localAssignmentFiles = [];
        $nextCw = date('W', strtotime('next week'));
        $currentCw = date('W', strtotime('this week'));

        $this->_logger->info('downloading assignment file(s)');
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

        $products = [];
        foreach ($localAssignmentFiles as $campaign => $localAssignmentFile) {
            $productIds = [];
            $validityStart = null;
            $validityEnd = null;
            $headerOver = false;
            $stores = null;
            $handle = fopen($localAssignmentFile, "r");
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $explodedLine = explode(';', $line);
                    // skipping empty lines in assignment csv
                    if (empty($explodedLine[0])) {
                        continue;
                    }

                    if ($headerOver) {
                        $productIds[$explodedLine[0]] = null;
                    } else {
                        if (preg_match('#ltig von#', $explodedLine[0])) {
                            $validityStart = $explodedLine[1];
                        } elseif (preg_match('#ltig bis#', $explodedLine[0])) {
                            $validityEnd = $explodedLine[1];
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

            if ($validityStart == null or $validityEnd == null or $stores == null) {
                $this->_logger->err('parsing of header in assignment file failed: SKIPPING ' . $campaign);
                continue;
            }

            $foundProductIdsInAssignmentFile = count($productIds);
            $this->_logger->info('found: ' . $foundProductIdsInAssignmentFile . ' products in assignment file: ' . $campaign);

            $rawProducts = json_decode(file_get_contents($localProductFeed), true);
            foreach ($rawProducts['produkte'] as $rawProduct) {
                if (array_key_exists($rawProduct['artikelcode'], $productIds)) {
                    $articleCodePlusCampaign = $rawProduct['artikelcode'] . '-' . $campaign;
                    $products[$articleCodePlusCampaign] = $rawProduct;
                    $products[$articleCodePlusCampaign]['start'] = $validityStart;
                    $products[$articleCodePlusCampaign]['end'] = $validityEnd;
                    $products[$articleCodePlusCampaign]['stores'] = $stores;
                    unset($productIds[$rawProduct['artikelcode']]);
                }
            }

            if (count($productIds) > 0) {
                foreach ($productIds as $productId => $null) {
                    $this->_logger->warn('missing "artikelcode" in product datafeed: ' . $productId . ', campaign: ' . $campaign);
                }
            }

            $this->_logger->info('parsed: ' . ($foundProductIdsInAssignmentFile - count($productIds)) . ' products from product data feed');

            $threshold = 0.25;
            if ((count($productIds) / $foundProductIdsInAssignmentFile) > $threshold) {
                throw new Exception('more than ' . $threshold . '% product "artikelcodes" from the assignment file: ' . $campaign . ' were not part of the product datafeed');
            }
        }

        $this->_logger->info("importing " . count($products) . ' products');

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($products as $productNumber => $product) {
            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setArticleNumber($productNumber)
                ->setManufacturer($product['hersteller'])
                ->setTitle($product['bezeichnung'])
                ->setUrl($product['url'])
                ->setImage($product['bildurl'])
                ->setPrice($product['abgabepreis'])
                ->setShipping($product['versandkosten'])
                ->setText($product['artikelbeschreibung'])
                ->setEan($product['ean'])
                ->setStoreNumber($product['stores'])
                ->setVisibleStart($product['start'])
                ->setStart($product['start'])
                ->setVisibleEnd($product['end'])
                ->setEnd($product['end']);

            $cArticles->addElement($eArticle,TRUE, 'complex', FALSE);
        }

        return $this->getResponse($cArticles, $companyId);
    }
}
