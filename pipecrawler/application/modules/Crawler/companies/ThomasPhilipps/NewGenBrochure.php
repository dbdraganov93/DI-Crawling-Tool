<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';


/**
 * NewGen Brochure Crawler für ThomasPhilipps (ID: 352, stage: 352)
 */
class Crawler_Company_ThomasPhilipps_NewGenBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $month = 'this';
        $sGS = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sBlender = new Blender($companyId);

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#[^\.]+\.pdf#', $singleFile)) {
                $localBrochurePath = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        $sFtp->close();

        $aApiData = $sApi->getActiveArticleCollection($companyId);

        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {

            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }


        $aData = $sGS->getFormattedInfos('1laGYSONeFBTNTWgTZ1nvCYNfjK_oqzjq8nvjKvk4dUk', 'A1', 'L', 'Worksheet');

        $aNewGen = [];
        foreach ($aData as $singleRow) {
            /*
            $priority = rand(2, 3);
            if (preg_match('#1#', $singleRow['Layout Priority'])) {
                $priority = 1;
            } */
            $priority = $singleRow['box_number'];

            $aNewGen[$singleRow['page_nr']]['articles'][] = [
                'articleNumber' => $aArticleIds[$singleRow['articlenumber']],
                'pageMetaphor' => $singleRow['category'],
                'priority' => $priority,
                'article_id' => $aArticleIds[$singleRow['articlenumber']]
            ];
            if($aArticleIds[$singleRow['articlenumber']] == null) {
                Zend_Debug::dump($singleRow['articlenumber']);
            }

            $aMetaphors[$singleRow['page_nr']][] = $singleRow['category'];
            $aMetaphors[$singleRow['page_nr']] = array_unique($aMetaphors[$singleRow['page_nr']]);
        }

        foreach($aMetaphors as $pageNr => $pageMetaphor) {
            $aNewGen[$pageNr]['pageMetaphor'] = implode(" / ",$pageMetaphor);
        }


        $strLayout = $sBlender->blend($aNewGen);
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Zauberhafte Winterzeit')
            ->setBrochureNumber( 'Discover_TP_2020_11_12')#date('Y', strtotime($month . ' month')) . '-' . date('m', strtotime($month . ' month')) . '-NG')
            ->setUrl($localBrochurePath)
            ->setVariety('leaflet')
            ->setStart('17.11.2020'     ) #date('d.m.Y', strtotime('first day of ' . $month . ' month')))
            ->setEnd('31.12.2020') #date('d.m.Y', strtotime('last day of ' . $month . ' month')))
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }

}