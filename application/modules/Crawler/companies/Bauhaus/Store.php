<?php

/**
 * Store Crawler für Bauhaus (ID: 577)
 */
class Crawler_Company_Bauhaus_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.bauhaus.info/';
        $searchUrl = $baseUrl . '/ajax/fachcentren/fachcentrensuche?q=:relevance:radius:30';
        $sPage = new Marktjagd_Service_Input_Page();
        $aCampaigns = [
            'OG + bonial' => '534,639,864,571,634,853,635,498,651,607,630,528,595,619,620,624,654,663,664,851,858,865,666,668,553,647,863,617,868,491,566,618,505,539,519,648,535,541,633,579,616,665,608,569,629,609,856,657,890,891,892,628,637,879,507,509,573,625,555,562,586,613,661',
            'OG only' => '485,508,580,587,599,626,631,871,875,330,525,852,530,543,544,547,584,590,591,594,621,642,649,662,515,632,862,548,520,870,561,567,859,568,572,598,606,861'
        ];

        $oPage = $sPage->getPage();
        $oPage->setAlwaysHtmlDecode(false);
        $sPage->setPage($oPage);

        $sPage->open($searchUrl);
        $aStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStores->location as $jStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($jStore->nr)
                ->setLatitude($jStore->lat)
                ->setLongitude($jStore->lon)
                ->setStreetAndStreetNumber($jStore->street)
                ->setZipcode($jStore->zip)
                ->setCity($jStore->city)
                ->setPhoneNormalized($jStore->phone)
                ->setWebsite($baseUrl . $jStore->url_detail)
                ->setToilet("1")
                ->setStoreHoursNormalized(implode($jStore->opening));

            foreach ($aCampaigns as $singleRegionName => $storeNumbers) {
                $aStoreNumbers = preg_split('#\s*,\s*#', $storeNumbers);
                if (in_array($eStore->getStoreNumber(), $aStoreNumbers)) {
                    $eStore->setDistribution($singleRegionName);
                    break;
                }
            }

            $sPage->open($eStore->getWebsite());
            $detailPage = $sPage->getPage()->getResponseBody();

            $patternMail = '#<b>\s*E\-Mail\:\s*</b>\s*<span[^>]*>\s*(.*?)\s*</span>#';
            if (preg_match($patternMail, $detailPage, $matchMail)) {
                $eStore->setEmail($matchMail[1]);
            }

            $patternOpeningArena = '#<p[^>]*class="opening"[^>]*>\s*<b>.*?ffnungszeiten\s*DRIVE.*?</b>.*?<span[^>]*>(.*?)</span>#';
            if (preg_match($patternOpeningArena, $detailPage, $matchOpeningArena)) {
                $eStore->setStoreHoursNotes('Öffnungszeiten DRIVE IN ARENA: ' . $matchOpeningArena[1]);
            }

            // add public transport
            $patternTransport = '#<div[^>]*class="publictransport"[^>]*>(.*?)</div>#s';
            if (preg_match($patternTransport, $detailPage, $matchTransport)) {
                if (preg_match_all('#<li[^>]*>(.*?)</li>#s', $matchTransport[1], $matchTransportItem)) {
                    $sTransport = 'Verkehrsanbindung: ';
                    foreach ($matchTransportItem[1] as $transportItem) {
                        if (strlen($sTransport)) {
                            $sTransport .= '<br>';
                        }

                        $sTransport .= $transportItem;
                    }

                    $eStore->setText($sTransport);
                }
            }

            // add assortment
            $searchAssortment = array('Dekoration', 'Fliesen', 'Holz', 'Pflanzen',
                'Pools', 'Laminat', 'Tapeten', 'Türen', 'Regale',
                'Folien', 'Dünger');
            $patternAssortment = '#<section[^>]*role="article"[^>]*>(.*?)</section>#s';

            if (preg_match($patternAssortment, $detailPage, $matchAssortment)) {
                $patternAssortmentItem = '#<li[^>]*>(.*?)</li>#s';
                if (preg_match_all($patternAssortmentItem, $matchAssortment[1], $matchAssortmentItem)) {
                    $sAssortment = '';
                    foreach ($matchAssortmentItem[1] as $assortmentItem) {
                        if (in_array($assortmentItem, $searchAssortment)) {
                            if (strlen($sAssortment)) {
                                $sAssortment .= ', ';
                            }
                            $sAssortment .= $assortmentItem;
                        }
                    }

                    $eStore->setSection($sAssortment);
                }
            }

            $pattern = '#id="service"[^>]*>(.+?)</section#';
            $strService = '';
            $strPayment = '';
            if (preg_match($pattern, $detailPage, $serviceListMatch)) {
                $pattern = '#<li[^>]*>(.+?)</li#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    foreach ($serviceMatches[1] as $singleService) {
                        if (strlen($strService . $singleService) >= 490) {
                            $strService .= '...';
                            break;
                        }
                        if (preg_match('#(Kartenzahlung|Kreditkarte|Finanzkauf)#', $singleService)) {
                            continue;
                        }
                        if (strlen($strService)) {
                            $strService .= ', ';
                        }
                        $strService .= $singleService;
                    }
                    foreach ($serviceMatches[1] as $singleService) {
                        if (strlen($strPayment . $singleService) >= 490) {
                            $strPayment .= '...';
                            break;
                        }
                        if (preg_match('#\-?\s*(Kartenzahlung.+|Kreditkarte.+|Finanzkauf.+)#', $singleService, $paymentMatch)) {
                            if (strlen($strPayment)) {
                                $strPayment .= ', ';
                            }
                            $strPayment .= $paymentMatch[1];
                        }
                    }
                }
                $strService = str_replace('- ', '', $strService);
                $eStore->setService($strService)
                    ->setPayment($strPayment);
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}