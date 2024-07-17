<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für Ikea AT (ID: 73466)
 */
class Crawler_Company_IkeaAt_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        #########################################################################
        # HOW IT WORKS:                                                         #
        #                                                                       #
        # upload the cover_pages onto our ftp server (folder 73466/Discover)       #
        #                                                                       #
        # adjust the variables as needed                                        #
        #########################################################################


        $campaigns = [
            1 => [
                'article_url' => 'pup-feed.csv',
                'campaign_title' => 'IKEA - Neuer gesenkter Preis',
                'brochure_number' => 'ikea_discover_NGP',
                'campaign_header' => 'a4_NLP.pdf',
                'start_date' => '06.11.2023',
                'end_date' => '06.12.2023',
            ],
        ];

        # services
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $sFtp->connect($companyId);
        $sFtp->changedir('Discover');

        foreach ($campaigns as $campaign) {
            $this->_logger->info("processing campaign: " . $campaign['campaign_title']);

            #get the campaign's cover page
            $this->_logger->info('downloading and transforming header image');
            $localCoverPage = $sFtp->downloadFtpToDir($campaign['campaign_header'], $localPath);

            #get the campaign's article file
            $this->_logger->info('downloading assignment file');
            $localArticleFile = $sFtp->downloadFtpToDir($campaign['article_url'], $localPath);
            $aData = $sPss->readFile($localArticleFile, TRUE, "\t")->getElement(0)->getData();


            $discover = [];
            $i = 0;
            foreach ($aData as $singleRow) {
                $i++;
                if ($i > 80) {
                    break;
                }
                $pageMetaphor = 'IKEA Angebote';
                $discover[$pageMetaphor]['page_metaphor'] = $pageMetaphor;

                $articleNumber = 'DISCOVER_' . $singleRow['id'];
                $this->_logger->info("processing article: $articleNumber");

                $apiArticle = $sApi->findArticleByArticleNumber($companyId, $articleNumber);
                if ($apiArticle == false or !array_key_exists('id', $apiArticle)) {
                    $this->_logger->err("error querying article from API, article_number: $articleNumber. SKIPPING ARTICLE");
                    continue;
                }

                $discover[$pageMetaphor]['products'][] = [
                    'product_id' => $apiArticle['id'],
                    'priority' => rand(1, 3)
                ];
            }

            # the service in the new framework
            $response = Blender::blendApi($companyId, array_values($discover), $campaign['brochure_number']);

            if ($response['http_code'] != 200) {
                $this->_logger->err($response['error_message']);
                continue;
            } else {
                $strLayout = $response['body'];
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle($campaign['campaign_title'])
                ->setBrochureNumber($campaign['brochure_number'])
                ->setUrl($localCoverPage)
                ->setVariety('leaflet')
                ->setStart($campaign['start_date'])
                ->setEnd($campaign['end_date'])
                ->setVisibleStart($eBrochure->getStart())
                ->setLayout($strLayout);

            $cBrochures->addElement($eBrochure);
        }
        return $this->getResponse($cBrochures);
    }
}
