<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover Brochure Crawler for Bosch AT (ID: 80219)
 * see https://offerista.slab.com/posts/bosch-at-80219-discover-articles-3zpfkjlv
 */
class Crawler_Company_BoschAt_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aStores = [
            'P',
            'SB-P',
            'DZ,SCS,W'
        ];

        $aInfos = $sGSRead->getCustomerData('BoschAT');

        # create a list with active articles
        $aApiData = $sApi->getActiveArticleCollection($companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }


        # download the cover page, articles file and the clickout file from FTP
        $localPath = $sFtp->connect($companyId . '/discover', TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match("#{$aInfos['brochureFileName']}$#", $singleFile)) {
                $localBrochureFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }

            if (preg_match("#{$aInfos['articleFileName']}$#", $singleFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }
        $sFtp->close();

        # read the excel files
        $aData = $sPss->readFile($localArticleFile, TRUE,)->getElement(0)->getData();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        /*
       * $filteredArray call back takes only 3 stores by PDM's request. To turn back to normal use $cStores with getElements call
         * in following foreach
       */

        foreach ($aStores as $singleStore) {
            # create the discover input array
            $aNewGen = [];
            foreach ($aData as $singleRow) {
                # get the product's start and end date to match the campaign date
                $startDate = $startDate ?? $singleRow['start'];
                $endDate = $endDate ?? $singleRow['end'];

                if (strtotime($singleRow['start']) >= strtotime('2022-11-24')
                    && strtotime('now') < strtotime('2022-11-24')) {
                    continue;
                }

                $aNewGen[$singleRow['page']]['page_metaphor'] = $singleRow['category'];
                $aNewGen[$singleRow['page']]['products'][] = [
                    'product_id' => $aArticleIds[$singleRow['article_number'] . '_' . $singleStore . '_Disc'],
                    'priority' => rand(1, 3),
                ];

                ksort($aNewGen);

            }

            # the service in the new framework
            $response = Blender::blendApi(80219, $aNewGen, $aInfos['brochureNumber']);


            if ($response['http_code'] != 200) {
                $this->_logger->err($response['error_message']);
                throw new Exception('blender api did not work out');
            }

            if (strlen($s3Url)) {
                $filePath = $s3Url;
            }

            $strLayout = $response['body'];
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle($aInfos['brochureTitle'])
                ->setBrochureNumber($aInfos['brochureNumber'] . '_' . $singleStore)
                ->setUrl($filePath)
                ->setVariety('leaflet')
                ->setStart($aInfos['validStart'])
                ->setEnd($aInfos['validEnd'])
                ->setVisibleStart($eBrochure->getStart())
                ->setLayout($strLayout);

            if ($cBrochures->addElement($eBrochure)) {
                $s3Url = $eBrochure->getUrl();
            }

        }

        return $this->getResponse($cBrochures);
    }
}

// SCS - Vösendorf: "https://ad.doubleclick.net/ddm/trackimp/N831858.1914422WOGIBTSWAS.AT/B26350261.312031994;dc_trk_aid=504868383;dc_trk_cid=156536918;ord=%%CACHEBUSTER%%;dc_lat=;dc_rdid=;tag_for_child_directed_treatment=;tfua=;gdpr=0;gdpr_consent=0;ltd=?"
// SB-P - Graz: "https://ad.doubleclick.net/ddm/trackimp/N831858.1914422WOGIBTSWAS.AT/B26350261.312031994;dc_trk_aid=504868383;dc_trk_cid=156537161;ord=%%CACHEBUSTER%%;dc_lat=;dc_rdid=;tag_for_child_directed_treatment=;tfua=;gdpr=0;gdpr_consent=0;ltd=?"
// P - Linz: "https://ad.doubleclick.net/ddm/trackimp/N831858.1914422WOGIBTSWAS.AT/B26350261.312031994;dc_trk_aid=504868383;dc_trk_cid=156537434;ord=%%CACHEBUSTER%%;dc_lat=;dc_rdid=;tag_for_child_directed_treatment=;tfua=;gdpr=0;gdpr_consent=0;ltd=?"
// W - Wien, Mariahilf: "https://ad.doubleclick.net/ddm/trackimp/N831858.1914422WOGIBTSWAS.AT/B26350261.312031994;dc_trk_aid=504868383;dc_trk_cid=156538082;ord=%%CACHEBUSTER%%;dc_lat=;dc_rdid=;tag_for_child_directed_treatment=;tfua=;gdpr=0;gdpr_consent=0;ltd=?"
// DZ: "https://ad.doubleclick.net/ddm/trackimp/N831858.1914422WOGIBTSWAS.AT/B26350261.312031994;dc_trk_aid=504868383;dc_trk_cid=156578113;ord=%%CACHEBUSTER%%;dc_lat=;dc_rdid=;tag_for_child_directed_treatment=;tfua=;gdpr=0;gdpr_consent=0;ltd=?"
