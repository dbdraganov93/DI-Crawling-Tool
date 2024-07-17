<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover Brochure Crawler für Picnic (ID: 82423)
 */
class Crawler_Company_Picnic_DiscoverBrochure extends Crawler_Generic_Company
{

    private string $localArticleFile;
    private string $localBrochurePath;

    public function crawl($companyId)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aInfos = $sGSRead->getCustomerData('picnicGer');

        $localPath = $sFtp->connect($companyId . '/Discover', true);
        $aApiData = $sApi->getActiveArticleCollection($companyId);

        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            if (strtotime($eApiData->getStart()) > strtotime('now')) {
                $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
            }
        }

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.xml$#', $singleFile)) {
                $this->localArticleFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }
            if (preg_match("#" . $aInfos['coverPage'] . "#", $singleFile)) {
                $this->localBrochurePath = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }

        }

        $sFtp->close();

        $xml = file_get_contents($this->localArticleFile);
        $data = simplexml_load_string($xml);

        $aData = [];
        foreach ($data as $item) {
            $singleProduct = array();
            foreach ($item as $key => $value) {
                $singleProduct[(string)$key] = (string)$value;
            }
            $aData[] = $singleProduct;

        }

        $aNewGen = [];
        foreach ($aData as $singleRow) {
            if (!is_null($aNewGen[$singleRow['pageNumber']]['products']) && count($aNewGen[$singleRow['pageNumber']]['products']) > 10) {
                continue;
            }
            $aNewGen[$singleRow['pageNumber']]['products'][] = [
                'priority' => rand(1, 3),
                'product_id' => $aArticleIds[$aInfos['brochureNumber'] . $singleRow['articleNumber']]
            ];
            $aNewGen[$singleRow['pageNumber']]['page_metaphor'] = $singleRow['category'];

            if (!$aArticleIds['Disc_' . $singleRow['articleNumber']]) {
                $this->_logger->warn('fehlende Artikelnummer ' . $singleRow['articleNumber']);
            }
        }

        ksort($aNewGen);

        $response = Blender::blendApi($companyId, $aNewGen, $aInfos['brochureNumber']);

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        $strLayout = $response['body'];

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle($aInfos['title'])
            ->setBrochureNumber($aInfos['brochureNumber'])
            ->setUrl($this->localBrochurePath)
            ->setVariety('leaflet')
            ->setStart($aInfos['validStart'])
            ->setEnd($aInfos['validEnd'])
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }


}