<?php
/**
 * Brochure Crawler für Billa AT (ID: 73282)
 */

class Crawler_Company_BillaAt_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.billa.at/';
        $searchUrl = $baseUrl . 'api/flipbook/weekly/flipbooks';
        $brochureDetailUrl = 'https://www.yumpu.com/de/document/json/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $week = 'this';

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);

        $sFtp->connect($companyId);
        $localTemplateFile = '';
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#KW' . date('W', strtotime('thursday ' . $week . ' week')) . '_linked.pdf#', $singleFile)) {
                $localTemplateFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }
        $sFtp->close();

        $sPage->open($searchUrl);
        $jInfos = $sPage->getPage()->getResponseAsJson();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($jInfos->flipbooks as $county => $aBrochures) {

            foreach ($aBrochures as $singleBrochure) {
                if (!preg_match('#kw' . date('W', strtotime('thursday ' . $week . ' week')) . '#', $singleBrochure->coverImageUrl)
                || preg_match('#Osttirol#', $singleBrochure->section)) {
                    continue;
                }
                $sPage->open($brochureDetailUrl . $singleBrochure->id);
                $jBrochureDetailInfo = $sPage->getPage()->getResponseAsJson();

                $downloadUrl = $jBrochureDetailInfo->document->download_url;
                if (!$sHttp->getRemoteFile($downloadUrl, $localPath, $singleBrochure->id . '.pdf')) {
                    $this->_logger->err($companyId . ': unable to get brochure ' . $singleBrochure->id . ' for county ' . $county);
                    continue;
                }
                $localBrochurePath = $localPath . $singleBrochure->id . '.pdf';

                if (strlen($localTemplateFile)) {
                    $this->_logger->info($companyId . ': template file found. copy links to ' . $localBrochurePath);
                        $localBrochurePath = $sPdf->copyLinks($localTemplateFile, [$localBrochurePath])[0];
                }

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setUrl($localBrochurePath)
                    ->setTitle('Billa: Wochenangebote')
                    ->setBrochureNumber($singleBrochure->id)
                    ->setDistribution($singleBrochure->section)
                    ->setStart($singleBrochure->campaignValidFrom)
                    ->setEnd($singleBrochure->campaignValidTo)
                    ->setVisibleStart($eBrochure->getStart())
                    ->setVariety('leaflet');

                $cBrochures->addElement($eBrochure);
            }
        }

        return $this->getResponse($cBrochures, $companyId);
    }

}