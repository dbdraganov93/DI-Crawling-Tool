<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';
/**
 * Discover für Rofu (ID: 28773)
 */

class Crawler_Company_Rofu_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # Upload the article csv file onto our FTP server (folder 'Discover')   #
        #                                                                       #
        # adjust articleFile                                                    #
        #########################################################################


        $csvFile = "2021-11/Feed_Cyber_Monday_2021.csv";
        $pdfFile = "2021-11/211125_ROFU_DiscoverHeader.pdf";

        $campaignName = 'ROFU Kinderland: Freu-Tag';

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localFolder = $sFtp->connect($companyId, TRUE);
        $localArticleFile = $sFtp->downloadFtpToDir( './'.$csvFile , $localFolder);
        $localpdfFile = $sFtp->downloadFtpToDir( './'.$pdfFile , $localFolder);



        $aApiData = $sApi->getActiveArticleCollection($companyId);

        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        $aData = $sPss->readFile($localArticleFile, TRUE, '|')->getElement(0)->getData();



        $aNewGen = [];
        foreach ($aData as $singleRow) {

            if(!$aArticleIds['DISCOVER_' . $singleRow['artikelnummer']]) {
                $this->_logger->warn('fehlende Artikelnummer ' . 'DISCOVER_' . $singleRow['artikelnummer']);
                continue;
            }

            $aNewGen[1]['page_metaphore'] = 'Freu-Tag';
            $aNewGen[1]['products'][] = [
                'product_id' => $aArticleIds['DISCOVER_' . $singleRow['artikelnummer']],
                'priority' => rand(1,3),
            ];
        }

        $response = Blender::blendApi($companyId, $aNewGen, $campaignName);

        $strLayout = $response['body'];

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Sonstige Angebote')
            ->setBrochureNumber($campaignName)
            ->setUrl($localpdfFile)
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);


        # move the PDF file after the creation of the Discover
        #$sFtp->move('./' . $pdfFile, './archive/' . $pdfFile);
        #$sFtp->close();

        return $this->getResponse($cBrochures, $companyId);
            }


}