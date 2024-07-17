<?php
/**
 * Artikelcrawler für Aetka (ID: 421)
 */
class Crawler_Company_Aetka_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $cArticle = new Marktjagd_Collection_Api_Article();
        $sFtp = new Marktjagd_Service_Transfer_Ftp();
        $aData = array(
            'hostname' => 'ftp.komsa.net',
            'username' => 'aet-ecom',
            'password' => 'WgabgzVM'
        );

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $aStores = $sApi->findAllStoresForCompany($companyId);

        $sFtp->connect($aData);
        $aFiles = $sFtp->listFiles();
        $iActualFile = 0;
        
        foreach ($aFiles as $singleFile) {
            if (preg_match('#Angebote\_marktjagd\_(.+?)\.csv#', $singleFile, $fileMatch)) {
                if ((int)preg_replace('#\_#', '', $fileMatch[1]) > (int)preg_replace('#\_#', '', $iActualFile)) {
                    $iActualFile = $fileMatch[1];
                }
            }
        }
        
        $filePath = $sFtp->downloadFtpToCompanyDir('Angebote_marktjagd_' . $iActualFile . '.csv', $companyId);
        $handle = fopen($filePath, "r");
        $header = null;

        while (($data = fgetcsv($handle, 50000, ",")) !== FALSE) {            
            if (!$header){
                $header = $data;
                continue;
            }

            $line = array_combine($header, $data);

            /**
             * skip articles with no valid store
             */
            $valid = false;
            foreach ($aStores as $oStore) {
                if ($oStore['number'] == $line['KNR']) {
                    $valid = true;
                    break;
                }
            }

            if (!$valid) {
                continue;
            }

            /**
             * skip defined categories
             */
            if (preg_match('#Displayschutz#is', $line['category name level 1'])
                || preg_match('#Taschen\s*&\s*Schutzh.+?llen#is', $line['category name level 1'])
                || preg_match('#Einweg\-Batterien#is', $line['category name level 1'])
                || preg_match('#Kabel\s*&\s*Adapter#is', $line['category name level 1'])
                || preg_match('#Telefonanlagen#is', $line['category name level 1'])
                || $line['category name level 1'] == ""
            ) {
                continue;
            }

            $aCategories[] = $line['category name level 1'];

            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setStoreNumber($line['KNR'])
                     ->setTitle($line['TITLE'])
                     ->setUrl($line['Link']);

            $sText = new Marktjagd_Service_Text_TextFormat();

            $text = $sText->htmlDecode(
                    strip_tags(
                        $line['Langtext_1'], '<br><br/><br />'
                ));
            
            $text = preg_replace(
                array(
                    '#\x93#',
                    '#\x94#',
                    '#\x99#',
                    '#_x000D_#'
                ),
                array(
                    '"',
                    '"',
                    '™',
                    '<br />'
                ),
                $text);
            
            $eArticle->setText($text)
                    ->setPrice($line['CURRENT PRICE'])
                    ->setEan($line['EAN'])
                    ->setStart($line['START DATE'])
                    ->setVisibleStart($eArticle->getStart())
                    ->setEnd($line['END DATE'])
                    ->setArticleNumber(end(explode('/', $line['Link'])))
                    ->setTags($line['category name level 1']);
            
            $images = array();
            if (trim($line['Detailbild1'])) {
                $images[] = trim($line['Detailbild1']);
            }
            if (trim($line['Detailbild2'])) {
                $images[] = trim($line['Detailbild2']);
            }
            if (trim($line['Detailbild3'])) {
                $images[] = trim($line['Detailbild3']);
            }

            if ($images) {
                $images = implode(',',$images);
            } else {
                $images = '';
            }

            $eArticle->setImage($images);
            $cArticle->addElement($eArticle);
            
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticle);
        $this->_response->generateResponseByFileName($fileName);
        return $this->_response;
    }
}