<?php
/**
 * Brochure Creation Skript für Lensvision CH (ID: 72228)
 */

class Crawler_Company_LensvisionCh_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();

        $aArticles = $sApi->findActiveArticlesByCompany($companyId);

        $sFtp->connect();
        $sFtp->changedir('./templates/' . $companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.pdf$#', $singleFile)) {
                $templateFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }

            if (preg_match('#srp_72228.png#', $singleFile)) {
                $suggestedRetailLogo = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }
        }

        $sFtp->close();

        $infoFields = $sPdf->getAnnotationInfos($templateFile);
        $countArticles = count($infoFields);
        $count = 0;
        $aArticlesInfos = array();
        $aExchange = array();
        foreach ($aArticles as $key => $singleArticle) {
            if (preg_match('#lastModified#', $key)
                || strlen($singleArticle['title']) > 40) {
                continue;
            }

            $pattern = '#(http[^\?]+)\?#';
            if (!preg_match($pattern, $singleArticle['url'], $urlMatch)) {
                $this->_logger->info($companyId . ': unable to get article url: ' . $singleArticle['title']);
                continue;
            }

            $sPage->open($urlMatch[1]);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="picture\s*Product__Image"[^>]*>(.+?)</div#';
            if (!preg_match($pattern, $page, $imageListMatch)) {
                $this->_logger->info($companyId . ': unable to get image list: ' . $singleArticle['title']);
                continue;
            }

            $pattern = '#<img[^>]*itemprop="image"[^>]*src="([^"]+?)"#';
            if (!preg_match($pattern, $imageListMatch[1], $imageMatch)) {
                $this->_logger->info($companyId . ': unable to get image from list: ' . $singleArticle['title']);
                continue;
            }

            $localImagePath = $sHttp->getRemoteFile($imageMatch[1], $localPath);

            $pos = strpos($singleArticle['title'], ' ');
            $part1 = substr($singleArticle['title'], 0, $pos);
            $part2 = substr($singleArticle['title'], $pos + 1);

            $aArticlesInfos[] =
                array(
                    'page' => $infoFields[$count]->page,
                    'startX' => $infoFields[$count]->rectangle->startX + 5,
                    'startY' => $infoFields[$count]->rectangle->startY + 5,
                    'endX' => $infoFields[$count]->rectangle->endX - 5,
                    'endY' => $infoFields[$count]->rectangle->endY - 5,
                    'type' => 'image',
                    'path' => $localImagePath,
                    'scaling' => TRUE
                );

            $aArticlesInfos[] =
                array(
                    'page' => $infoFields[$count]->page,
                    'startX' => $infoFields[$count]->rectangle->endX - 70,
                    'startY' => $infoFields[$count]->rectangle->endY - 70,
                    'endX' => $infoFields[$count]->rectangle->endX - 10,
                    'endY' => $infoFields[$count]->rectangle->endY - 10,
                    'type' => 'image',
                    'path' => $suggestedRetailLogo,
                    'scaling' => FALSE
                );

            $aArticlesInfos[] =
                array(
                    'page' => $infoFields[$count]->page,
                    'startX' => $infoFields[$count]->rectangle->endX - 64,
                    'startY' => $infoFields[$count]->rectangle->endY - 52,
                    'type' => 'text',
                    'contents' => preg_replace('#\.#', ',', number_format($singleArticle['price'], 2)),
                    'font' => array('fontType' => 'Helvetica_Bold', 'fontSize' => 16, 'fontColor' => '255|255|255')
                );

            $aArticlesInfos[] =
                array(
                    'page' => $infoFields[$count]->page,
                    'startX' => $infoFields[$count]->rectangle->endX - 64,
                    'startY' => $infoFields[$count]->rectangle->endY - 35,
                    'type' => 'text',
                    'contents' => 'CHF',
                    'font' => array('fontType' => 'Helvetica_Bold', 'fontSize' => 9, 'fontColor' => '255|255|255')
                );

            $aArticlesInfos[] =
                array(
                    'page' => $infoFields[$count]->page,
                    'startX' => $infoFields[$count]->rectangle->endX - 44,
                    'startY' => $infoFields[$count]->rectangle->endY - 35,
                    'type' => 'text',
                    'contents' => preg_replace('#\.#', ',', number_format($singleArticle['suggested_retail_price'], 2)),
                    'font' => array('fontType' => 'Helvetica', 'fontSize' => 9, 'fontColor' => '255|255|255')
                );

            $aArticlesInfos[] =
                array(
                    'page' => $infoFields[$count]->page,
                    'startX' => $infoFields[$count]->rectangle->endX - 45,
                    'startY' => $infoFields[$count]->rectangle->endY - 35,
                    'endX' => $infoFields[$count]->rectangle->endX - 15,
                    'endY' => $infoFields[$count]->rectangle->endY - 30,
                    'type' => 'line',
                    'line' => array('lineWidth' => 0.5, 'lineColor' => '255|255|255')
                );

            $aArticlesInfos[] =
                array(
                    'page' => $infoFields[$count]->page,
                    'startX' => $infoFields[$count]->rectangle->startX + 10,
                    'startY' => $infoFields[$count]->rectangle->startY - 5,
                    'type' => 'text',
                    'contents' => $part1,
                    'font' => array('fontType' => 'Helvetica_Bold', 'fontSize' => 15, 'fontColor' => '0|0|0')
                );

            $aArticlesInfos[] =
                array(
                    'page' => $infoFields[$count]->page,
                    'startX' => $infoFields[$count]->rectangle->startX + 10,
                    'startY' => $infoFields[$count]->rectangle->startY - 25,
                    'type' => 'text',
                    'contents' => $part2,
                    'font' => array('fontType' => 'Helvetica', 'fontSize' => 12, 'fontColor' => '0|0|0')
                );

            $aExchange[$count] =
                array(
                    'searchPattern' => $infoFields[$count]->url,
                    'replacePattern' => $singleArticle['url']
                );

            if ($count++ == $countArticles - 1) {
                break;
            }
        }

        $filePath = $localPath . 'template.json';
        $fh = fopen($filePath, 'w+');
        fwrite($fh, json_encode($aArticlesInfos));
        fclose($fh);

        $filledFile = $sPdf->addElements($templateFile, $filePath, preg_replace('#\.pdf#', '_added.pdf', $templateFile));

        $exchangeFilePath = $localPath . 'exchange.json';
        $fh = fopen($exchangeFilePath, 'w+');
        fwrite($fh, json_encode($aExchange));
        fclose($fh);

        $exchangedFile = $sPdf->modifyLinks($filledFile, $exchangeFilePath, FALSE, FALSE);

        Zend_Debug::dump($exchangedFile);
        die;

    }
}
