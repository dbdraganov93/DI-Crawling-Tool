<?php

/* 
 * Prospekt Crawler für Manor CH (ID: 72138)
 */

class Crawler_Company_ManorCh_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.manor.ch';
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTranslation = new Marktjagd_Service_Text_Translation();


        $aBrochure = array();
        if ($companyId == 72138) {
            $searchUrl = '/u/latest-catalogs';
            $aBrochure =
                array(
                    'multimedia' => array(
                        'de' => array(
                            'title' => 'Multimedia / Elektro / Home',
//                            'pattern' => '#<a[^>]*href="([^"]+)"[^>]*>ELEKTRO[^\/]*\/\s*'
//                                . '([^\-]*)\s*-\s*([0-9]{2}\.[0-9]{2}\.)([0-9]{4})[^<]*<\/a>#is'
                        ),
                        'fr' => array(
                            'title' => 'Multimédia / Électro / Home',
//                            'pattern' => '#<a[^>]*href="([^"]+)"[^>]*>ÉLECTRO[^\/]*\/\s*'
//                                . '([^\-]*)\s*-\s*([0-9]{2}\.[0-9]{2}\.)([0-9]{4})[^<]*<\/a>#is'
                        ),
                        'it' => array(
                            'title' => 'Multimedia / Apparecchi elettrici / Home',
//                            'pattern' => '#<a[^>]*href="([^"]+)"[^>]*>APPARECCHI\s*ELETTRICI[^\/]*\/\s*'
//                                . '([^\-]*)\s*-\s*([0-9]{2}\.[0-9]{2}\.)([0-9]{4})[^<]*<\/a>#is'
                        )
                    )
                );

        } else if ($companyId == 72250) {
            $searchUrl = '/u/angebote-der-woche';
            $aBrochure = array(
                'weekly' => array(
                    'de' => array(
                        'title' => 'Angebote der Woche',
//                        'pattern' => '#<a[^>]*href="([^"]+pdf[^"]*)"[^>]*>\s*ANGEBOTE\s*DER\s*WOCHE[^\/]*\/\s*'
//                            . '([^\-]*)\s*-\s*([0-9]{2}\.[0-9]{2}\.)([0-9]{4})[^<]*<\/a>#is'
                    ),
                    'fr' => array(
                        'title' => 'Offres de la semaine',
//                        'pattern' => '#<a[^>]*href="([^"]+)"[^>]*>\s*OFFRES\s*DE\s*LA\s*SEMAINE[^\/]*\/\s*'
//                            . '([^\-]*)\s*-\s*([0-9]{2}\.[0-9]{2}\.)([0-9]{4})[^<]*<\/a>#is'
                    ),
                    'it' => array(
                        'title' => 'Offerte della settimana',
//                        'pattern' => '#<a[^>]*href="([^"]+)"[^>]*>\s*OFFERTE\s*DELLA\s*SETTIMANA[^\/]*\/\s*'
//                            . '([^\-]*)\s*-\s*([0-9]{2}\.[0-9]{2}\.)([0-9]{4})[^<]*<\/a>#is'
                    )
                ),
            );
        }

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aBrochure as $brochureName => $aLanguages) {
            foreach ($aLanguages as $singleLanguage => $singleInfo) {
                $storeNumbers = [];
                /* @var $eStore Marktjagd_Entity_Api_Store */
                foreach ($cStores as $eStore) {
                    if (preg_match('#^' . $singleLanguage . '$#', $sTranslation->findLanguageCodeForZipcode($eStore->getZipcode()))) {
                        $storeNumbers[] = $eStore->getStoreNumber();;
                    }
                }

                $this->_logger->info($companyId . ': amount stores for language ' . $singleLanguage . ': ' . count($storeNumbers));

                $sPage->open($baseUrl . '/' . $singleLanguage . $searchUrl);
                $page = $sPage->getPage()->getResponseBody();

                foreach ($sPage->getUrlsFromText($page, '#(?:\.pdf?|\.pdf$)#', $baseUrl) as $link) {
                    $eBrochure = new Marktjagd_Entity_Api_Brochure();
                    $eBrochure->setUrl($link)
                        ->setBrochureNumber(substr(md5($link), 0, 24))
                        ->setTitle($singleInfo['title'])
                        ->setStart(date('d.m.Y'))
                        ->setEnd(date('d.m.Y'))
                        ->setVariety('leaflet')
                        ->setStoreNumber(implode(',', $storeNumbers))
                        ->setLanguageCode($singleLanguage);

                    $cBrochures->addElement($eBrochure);
                }
            }
        }
        return $this->getResponse($cBrochures, $companyId, 0);
    }
}
