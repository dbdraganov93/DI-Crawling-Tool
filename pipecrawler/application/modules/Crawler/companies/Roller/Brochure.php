<?php

/*
 * Brochure Crawler für Roller (ID: 76, 71709)
 */

class Crawler_Company_Roller_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $aCampaignZipcodes = [
            '12489' => 'B',
            '12623' => 'B',
            '14167' => 'B',
            '85386' => 'E',
            '90482' => 'N'
        ];

        $cStores = $sApi->findStoresByCompany($companyId);
        $aStores = [];
        foreach ($cStores->getElements() as $eStore) {
            if (array_key_exists($eStore->getZipcode(), $aCampaignZipcodes)) {
                $aStores['_extra_' . $aCampaignZipcodes[$eStore->getZipcode()]][] = $eStore->getStoreNumber();
            } else {
                $aStores['_regular'][] = $eStore->getStoreNumber();
            }
        }

        $sFtp->connect('76');
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($sFtp->listFiles() as $singleFile) {
            if (!preg_match('#(.*?KW(\d{2})([A-Z]{2})?[^/]+?extern)[^\.]*\.pdf$#', $singleFile, $nameMatch)) {
                continue;
            }

            if ($nameMatch[2] < date('W')) {
                continue;
            }
            foreach ($aStores as $key => $aStoreNumbers) {
                $year = 'this year';
                $localBrochure = $sFtp->downloadFtpToCompanyDir($singleFile, $companyId);

                if (preg_match('#_extra#', $key)) {
                    $brochureParameter = date('Wy');
                    if (count($nameMatch) == 4 && preg_match('#(GK)#', $nameMatch[3])) {
                        $brochureParameter = 'GK' . date('y');
                    }
                    $aReplaceData = array(
                        array(
                            'searchPattern' => '[^\&]+\&(url\=[^\?]+)\?.+',
                            'replacePattern' => 'https://m.exactag.com/cl.aspx?tc=e916f59ac9a66421b0c4b43a3747d612&$1?utm_campaign=crosschannel&utm_source=offerista&utm_medium=display&utm_content=prospekt' . $brochureParameter
                        )
                    );
                } else {
                    $aReplaceData = array(
                        array(
                            'searchPattern' => '(.+)\&?',
                            'replacePattern' => '$1?utm_campaign=Blaetterkatalog_prosp&utm_source=Marktjagd_extern&utm_medium=prospekt'
                        )
                    );
                }

                $jsonFile = APPLICATION_PATH . '/../public/files/76.json';
                $fh = fopen($jsonFile, 'w+');
                fwrite($fh, json_encode($aReplaceData));
                fclose($fh);

                $localBrochureExchanged = $sPdf->exchange($localBrochure);
                $localBrochureLinked = $sPdf->modifyLinks($localBrochureExchanged, $jsonFile);

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setUrl($localBrochureLinked)
                    ->setTitle('Wochen Angebote')
                    ->setVariety('leaflet')
                    ->setStoreNumber(implode(',', $aStoreNumbers))
                    ->setStart($sTimes->findDateForWeekday(date('Y', strtotime($year)), $nameMatch[2], 'Mo'))
                    ->setEnd($sTimes->findDateForWeekday(date('Y', strtotime($year)), $nameMatch[2], 'Sa'))
                    ->setVisibleStart($sTimes->findDateForWeekday(date('Y', strtotime($year)), $nameMatch[2] - 1, 'So'))
                    ->setBrochureNumber(substr(date('Y', strtotime($year)) . $nameMatch[1], 0, 15) . $key);

                if (preg_match('#_extra#', $key)) {
                    $eBrochure->setTrackingBug('https://m.exactag.com/ai.aspx?tc=e916f59ac9a66421b0c4b43a3747d612&url=&cb=%%CACHEBUSTER%%');
                }
                $cBrochures->addElement($eBrochure);
            }
        }
        return $this->getResponse($cBrochures, $companyId);
    }

}
