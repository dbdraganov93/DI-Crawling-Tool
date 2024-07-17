<?php

/*
 * Store Crawler für Saturn AT (ID: 73047)
 */

class Crawler_Company_SaturnAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.saturn.at/';
        $searchUrl = $baseUrl . 'de/marketselection.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li[^>]*>\s*<a[^>]*href="\/(mcs\/marketinfo\/_Saturn[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $attributesProps = [
            'streetAddress' => 'itemprop',
            'postalCode' => 'itemprop',
            'addressLocality' => 'itemprop',
            'latitude' => 'itemprop',
            'longitude' => 'itemprop',
            'email' => 'data-ya-track',
            'telephone' => 'itemprop',
            'faxNumber' => 'itemprop',
            'openingHours' => 'itemprop',
        ];

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            if (!preg_match('#.+?,(\d+?),#', $singleStoreUrl, $storeNumberMatch)) {
                $this->_logger->err($companyId . ': unable to get store number: ' . $baseUrl . $singleStoreUrl);
                continue;
            }
            $domPage = $sPage->getResponseAsDOM($sPage->getRedirectedUrl($baseUrl . $singleStoreUrl));

            $attributes = [];
            foreach ($attributesProps as $prop => $promName) {
                $attributes[$prop] = '';
                foreach ($sPage->getDomElsFromDomEl($domPage, $prop, $promName) as $attrRaw) {
                    switch ($prop) {
                        case 'postalCode':
                        case 'telephone':
                        case 'email':
                        case 'faxNumber':
                            if ($attrRaw->textContent != '') {
                                $attributes[$prop] = $attrRaw->textContent;
                                break 2;
                            }
                            break;
                        case 'openingHours':
                            $separator = ', ';
                            if ($attrRaw->hasAttribute('content') && $attrRaw->getAttribute('content')) {
                                $attributes[$prop] = trim($attributes[$prop] . $separator . $attrRaw->getAttribute('content'), $separator);
                            }
                            break;
                        default:
                            {
                                if ($attrRaw->hasAttribute('content') && $attrRaw->getAttribute('content')) {
                                    $attributes[$prop] = $attrRaw->getAttribute('content');
                                    break 2;
                                } elseif ($attrRaw->textContent) {
                                    $attributes[$prop] = $attrRaw->textContent;
                                    break 2;
                                }
                            }
                    }
                }
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setAddress($attributes['streetAddress'], $attributes['postalCode'] . ' ' . $attributes['addressLocality'], 'AT')
                ->setLatitude($attributes['latitude'])
                ->setLongitude($attributes['longitude'])
                ->setPhoneNormalized($attributes['telephone'])
                ->setFaxNormalized($attributes['faxNumber'])
                ->setEmail($attributes['email'])
                ->setWebsite($sPage->getRedirectedUrl($baseUrl . $singleStoreUrl))
                ->setStoreHoursNormalized($attributes['openingHours'])
                ->setStoreNumber($storeNumberMatch[1]);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}
