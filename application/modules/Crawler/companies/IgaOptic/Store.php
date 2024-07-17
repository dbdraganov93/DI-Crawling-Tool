<?php

/*
 * Store Crawler für Iga Optic (ID: 71819)
 */

class Crawler_Company_IgaOptic_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $site = 1;
        $baseUrl = 'http://www.igaoptic.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $aDetailUrls = array();

        while (true)
        {
            $searchUrl = $baseUrl . 'index.php?option=com_sobipro&sid=1&task=list.alpha.0-9.field_plz'
                    . '&site=' . $site . '&Itemid=0';
            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<span\s*class="spEntriesListTitle">\s*<a\s*href="\/([^"]+?sid=([0-9]+?)\:[^"]+?)"#';
            if (!preg_match_all($pattern, $page, $storeDetailUrlMatches))
            {
                $this->_logger->err($companyId . ': unable to get any store for site: ' . $site);
            }
            
            for ($i = 0; $i < count($storeDetailUrlMatches[1]); $i++)
            {
                $aDetailUrls[$storeDetailUrlMatches[2][$i]] = $baseUrl . $storeDetailUrlMatches[1][$i];
            }

            Zend_Debug::dump('read site no: ' . $site++);
            if (!preg_match('#<a[^>]*>\s*Letzte\s*Seite\s*<#', $page))
            {
                break;
            }
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aDetailUrls as $detailKey => $detailValue)
        {
            $sPage->open($detailValue);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#SPTitle"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $subtitleMatch))
            {
                $eStore->setSubtitle($subtitleMatch[1]);
            }
            
            $pattern = '#spFieldsData[^>]*>\s*<strong[^>]*>\s*([^\:]+?)\:\s*</strong>\s*(.+?)\s*<#';
            if (!preg_match_all($pattern, $page, $infoMatches))
            {
                $this->_logger->err($companyId . ': unable to get any store infos: ' . $detailValue);
                continue;
            }
            
            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);
            
            $eStore->setStreet($sAddress->extractAddressPart('street', $aInfos['Straße']))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aInfos['Straße']))
                    ->setZipcode($aInfos['PLZ'])
                    ->setCity($aInfos['Stadt'])
                    ->setPhone($sAddress->normalizePhoneNumber($aInfos['Telefon']))
                    ->setFax($sAddress->normalizePhoneNumber($aInfos['Fax']))
                    ->setWebsite(strip_tags($aInfos['Website']))
                    ->setEmail($sAddress->normalizeEmail($aInfos['Email']))
                    ->setStoreNumber($detailKey)
                    ->setWebsite($detailValue);
            
            $cStores->addElement($eStore);
            
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
