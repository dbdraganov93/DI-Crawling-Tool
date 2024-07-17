<?php
/**
 * Artikelcrawler für Allyouneed (ID: 71839)
 */
class Crawler_Company_Allyouneed_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $cArticle = new Marktjagd_Collection_Api_Article();
        $sCSV = new Marktjagd_Service_Input_PhpExcel();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        
        $filenameCSV = 'Marktjagd.csv';
        
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $remoteFile = 'http://s1.allyouneedfresh.de/datenfeed/Marktjagd/' . $filenameCSV;        
                
        $sHttp->getRemoteFile($remoteFile, $localPath);

        $artCSV = $sCSV->readFile($localPath . $filenameCSV, true, ';');
        $artElements = $artCSV->getElements();
        $artElements = $artElements[0]->getData();
                
        foreach ($artElements as $artElement){    
            $eArticle = new Marktjagd_Entity_Api_Article();
                        
            if (!Marktjagd_Service_Text_Encoding::checkValidUtf8($artElement['Description'])){
                $artElement['Description'] = preg_replace('#\x1D#', '', $artElement['Description']);
            }
             
            $eArticle->setArticleNumber($artElement['Number'])
                    ->setTitle($artElement['BrandName'] . ' ' . $artElement['Name'])
                    ->setText($artElement['Description'])
                    ->setPrice(str_replace(array('.', ','), array('', '.'), $artElement['SalesPrice']))
                    ->setEnd($artElement['Gültig bis'])
                    ->setSuggestedRetailPrice(str_replace(array('.', ','), array('', '.'), $artElement['WasPrice']))                    
                    ->setImage($artElement['BigImage'])
                    ->setTrademark($artElement['BrandName'])
                    ->setManufacturer($artElement['ManufacturerName'])
                    ->setEan($artElement['EAN'])
                    ->setUrl($artElement['Link'])
                    ->setTags(preg_replace('#^\s*\,#', '', preg_replace('#\s*(>|\/|\&)\s*#', ',', $artElement['Category'])));            
            
            if (!preg_match('#^0\.00#', trim($artElement['Basic Pricing']))){
                $eArticle->setAmount(preg_replace('#\s.+?\/#', ' EUR/', $artElement['Basic Pricing']));
            }
            
            //Zend_Debug::dump($eArticle);
            $cArticle->addElement($eArticle);
            
        }                       
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticle);
        $this->_response->generateResponseByFileName($fileName);
        return $this->_response;
    }
}