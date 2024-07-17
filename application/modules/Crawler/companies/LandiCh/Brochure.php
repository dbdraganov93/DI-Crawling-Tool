<?php

/* 
 * Prospekt Crawler für Landi CH (ID: 72173)
 */

class Crawler_Company_LandiCh_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://landi.ch/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sWidget = new Marktjagd_Service_Input_WidgetImport();

        $pattern = '#<h[^>]*>\s*([^<]+?)\s*<\/h[^>]*>\s*<[^>]*>\s*{{gueltig}}\s*{{ab}}\s*([^<]+?)\s*<\/p>.*\s*<div[^>]*data-configid="[^\/]+?\/([^"]+?)"#i';
        $replacements = array(
            'de' => array(
                '{{gueltig}}' => 'gültig',
                '{{ab}}' => 'ab',
            ),
            'fr' => array(
                '{{gueltig}}' => 'Dès',
                '{{ab}}' => 'le',),
        );
        $aLanguages = array(
            'de' => array(
                'searchUrl' => $baseUrl . 'laden/aktuell/gazette',
                'validityPattern' => strtr($pattern, $replacements['de'])
            ),
            'fr' => array(
                'searchUrl' => $baseUrl . 'fr/magasin/actuel/gazette',
                'validityPattern' => strtr($pattern, $replacements['fr'])
            ),
        );

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aLanguages as $singleLanguage => $singleInfo) {
            $this->_logger->info($companyId . ': opening ' . $singleInfo['searchUrl']);

            $sPage->open($singleInfo['searchUrl']);
            if (!preg_match($singleInfo['validityPattern'], $sPage->getPage()->getResponseBody(), $validityMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure validity for language: ' . $singleLanguage);
                continue;
            }

            foreach ($sWidget->getBrochureFromIssuu($singleInfo['searchUrl']) as $brochureId => $filePath) {
                $aTime = preg_split('#\.#', $sTimes->localizeDate($validityMatch[2], $singleLanguage));
                foreach ($aTime as &$singlePart) {
                    $singlePart = str_pad($singlePart, 2, '0', STR_PAD_LEFT);
                }
                $strDate = implode('.', $aTime);

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setUrl($filePath)
                    ->setTitle($validityMatch[1])
                    ->setStart($strDate)
                    ->setVariety('leaflet')
                    ->setBrochureNumber(preg_replace('#\s+#', '_', $eBrochure->getTitle()) . '_' . date('Y'))
                    ->setLanguageCode($singleLanguage)
                    ->setDistribution($singleLanguage);

                $cBrochures->addElement($eBrochure);
            }
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}