<?php
/**
 * Store Crawler für Point S FR (ID: 73519)
 */

class Crawler_Company_PointSFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.points.fr/';
        $searchUrl = $baseUrl . 'recherche.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#(var\s*lat\s*=\s*.+?)\s*\/\/var\s*point#';
        if (!preg_match_all($pattern, $page, $storeInfoMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeInfoMatches[1] as $singleStore) {
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[^"]+?)"#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address - ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#var\s*(lat|lng)\s*=\s*"([^"]+?)"#';
            if (preg_match_all($pattern, $singleStore, $geoMatches)) {
                $aGeo = array_combine($geoMatches[1], $geoMatches[2]);

                $eStore->setLatitude($aGeo['lat'])
                    ->setLongitude($aGeo['lng']);
            }

            $pattern = '#var\s*nomAgence\s*=\s*"([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $titleMatch)) {
                $eStore->setTitle($titleMatch[1]);
            }

            $pattern = '#tel\.?\s*:?\s*([^<]+?)<#i';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#fax\.?\s*:?\s*([^<]+?)<#i';
            if (preg_match($pattern, $singleStore, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2], 'fr');

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}